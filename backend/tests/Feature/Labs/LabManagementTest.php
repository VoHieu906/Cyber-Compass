<?php

use App\Models\AdminAuditLog;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\LearningActivity;
use App\Models\User;
use App\Modules\Labs\Events\LabCompleted;
use App\Modules\Labs\Services\LabDefinition;
use App\Modules\Labs\Services\LabDefinitionValidator;
use App\Modules\Labs\Services\LabFiles;
use App\Modules\Labs\Services\LabLoader;
use App\Modules\Labs\Services\LabScenarios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
| Lab management (F7, D73 to D79): one validator with a schema per engine, reference solutions
| replayed before anything goes live, a draft and publish lifecycle, database ownership, attempts
| pinned to a scenario version, and an admin preview with no learner side effects.
*/

const LAB_ENGINES = ['linux-shell', 'firewall-rules', 'http-inspector'];

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->learner = User::factory()->create();
});

/** @return array<string, mixed> */
function labStarter(string $engine, array $over = []): array
{
    $starter = app(LabScenarios::class)->starter($engine);

    return $over + [
        'title' => 'A lab', 'summary' => 'Summary.', 'briefing' => 'Briefing.', 'domain' => 'linux', 'lesson' => null,
        'engine' => $engine, 'difficulty' => 1, 'estimated_minutes' => 10, 'scenario' => $starter['scenario'], 'solutions' => $starter['solutions'],
    ];
}

function labAdmin(): void
{
    Auth::forgetGuards();
    test()->actingAs(test()->admin, 'web');
}

function labLearner(): void
{
    Auth::forgetGuards();
    test()->actingAs(test()->learner, 'web');
}

function labAdminData(string $slug): array
{
    labAdmin();

    return test()->getJson('/api/v1/admin/content/labs/'.Lab::query()->where('slug', $slug)->value('id'))->assertOk()->json('data');
}

/** Saves the working copy of a lab with changes made to its definition, and returns the response data. */
function labEdit(string $slug, Closure $change): array
{
    $lab = labAdminData($slug);
    $def = $change($lab['definition']);

    return test()->putJson("/api/v1/admin/content/labs/{$lab['id']}/draft", ['lock_version' => $lab['lock_version']] + $def)->assertOk()->json('data');
}

describe('the validator', function () {
    it('accepts every starter and replays its solutions and decoys', function (string $engine) {
        $report = app(LabDefinitionValidator::class)->check(labStarter($engine), replay: true);

        expect($report['errors'])->toBe([]);
    })->with(LAB_ENGINES);

    it('names the place of every invalid fixture', function (string $engine) {
        $cases = json_decode((string) file_get_contents(LabScenarios::directory()."/fixtures/{$engine}/invalid.json"), true);
        expect($cases)->not->toBeEmpty();

        foreach ($cases as $case) {
            $def = labStarter($engine);
            foreach ($case['set'] as $path => $value) {
                // Dotted paths into the definition; a file name with dots is the one key left whole.
                $keys = str_contains($path, '/') ? ['scenario', 'files', substr($path, strlen('scenario.files.'))] : explode('.', $path);
                $ref = &$def;
                foreach ($keys as $key) {
                    $ref = &$ref[$key];
                }
                $ref = $value;
                unset($ref);
            }
            $errors = array_keys(app(LabDefinitionValidator::class)->check($def)['errors']);
            expect(collect($errors)->contains(fn ($e) => str_starts_with($e, $case['error'])))->toBeTrue("{$engine}: {$case['name']} gave ".json_encode($errors));
        }
    })->with(LAB_ENGINES);

    it('accepts every seeded lab and replays its solutions', function () {
        foreach (LabFiles::read()['labs'] as $slug => $lab) {
            expect(app(LabDefinitionValidator::class)->check(LabDefinition::normalize($lab), replay: true)['errors'])->toBe([], $slug);
        }
    });

    it('refuses a lab nobody can solve, a decoy that wins, and a refused action', function () {
        $def = labStarter('linux-shell');
        $def['solutions']['solutions'][0]['actions'] = [['type' => 'command', 'command' => 'pwd']];
        $def['solutions']['decoys'][0]['actions'] = $def['solutions']['solutions'][0]['actions'];
        $errors = app(LabDefinitionValidator::class)->check($def, replay: true)['errors'];
        expect($errors)->toHaveKey('solutions.solutions.0');

        $winning = labStarter('linux-shell');
        $winning['solutions']['decoys'][0]['actions'] = $winning['solutions']['solutions'][0]['actions'];
        expect(app(LabDefinitionValidator::class)->check($winning, replay: true)['errors'])->toHaveKey('solutions.decoys.0');

        $refused = labStarter('firewall-rules');
        $refused['solutions']['solutions'][0]['actions'] = [['type' => 'teleport']];
        expect(app(LabDefinitionValidator::class)->check($refused, replay: true)['errors']['solutions.solutions.0'][0])->toContain('Action 1 was refused');

        $none = labStarter('firewall-rules');
        $none['solutions']['solutions'] = [];
        expect(app(LabDefinitionValidator::class)->check($none, replay: true)['errors'])->toHaveKey('solutions.solutions');
    });

    it('warns about a checkpoint that is already passed at the start', function () {
        $def = labStarter('firewall-rules');
        $def['scenario']['default'] = 'deny';

        expect(app(LabDefinitionValidator::class)->check($def, replay: true)['warnings'])->toHaveKey('scenario.checkpoints');
    });
});

describe('the admin API', function () {
    it('keeps guests and learners out of every route', function () {
        $this->getJson('/api/v1/admin/content/labs')->assertUnauthorized();
        labLearner();
        $this->getJson('/api/v1/admin/content/labs')->assertForbidden();
        $this->postJson('/api/v1/admin/content/labs', [])->assertForbidden();
        $this->postJson('/api/v1/admin/content/labs/1/preview')->assertForbidden();
    });

    it('lists the labs with their ownership and the engines on offer', function () {
        labAdmin();
        $json = $this->getJson('/api/v1/admin/content/labs')->assertOk()->json();

        expect(collect($json['data'])->pluck('slug')->all())->toBe(['investigate-a-suspicious-login', 'lock-down-a-web-server', 'inspect-a-shop-session'])
            ->and($json['data'][0])->toMatchArray(['status' => 'published', 'has_draft' => false, 'owned' => false])
            ->and(collect($json['meta']['engines'])->pluck('key')->all())->toBe(LAB_ENGINES);
    });

    it('creates a draft lab that learners cannot see until it is published', function () {
        labAdmin();
        $created = $this->postJson('/api/v1/admin/content/labs', ['slug' => 'my-first-lab', 'engine' => 'firewall-rules', 'title' => 'My first lab', 'domain' => 'linux'])
            ->assertCreated()->json('data');
        expect($created)->toMatchArray(['status' => 'draft', 'has_draft' => true, 'owned' => true, 'lock_version' => 1]);

        labLearner();
        expect(collect($this->getJson('/api/v1/labs')->json('data'))->pluck('slug')->all())->not->toContain('my-first-lab');
        $this->getJson('/api/v1/labs/my-first-lab')->assertNotFound();
        $this->postJson('/api/v1/labs/my-first-lab/attempts')->assertNotFound();

        labAdmin();
        $this->postJson("/api/v1/admin/content/labs/{$created['id']}/publish", ['lock_version' => 1])->assertOk()->assertJsonPath('data.status', 'published');
        labLearner();
        $this->getJson('/api/v1/labs/my-first-lab')->assertOk();
    });

    it('refuses a duplicate slug, an unknown engine and a bad slug', function () {
        labAdmin();
        foreach ([['slug' => 'lock-down-a-web-server'], ['slug' => 'Bad Slug'], ['engine' => 'quantum']] as $bad) {
            $this->postJson('/api/v1/admin/content/labs', $bad + ['slug' => 'ok-slug', 'engine' => 'firewall-rules', 'title' => 'T', 'domain' => 'linux'])->assertUnprocessable();
        }
    });

    it('saves drafts under a lock version and keys errors by path', function () {
        labAdmin();
        $lab = labAdminData('lock-down-a-web-server');
        $def = $lab['definition'];
        $def['scenario']['rules'][0]['port'] = 70000;

        $this->putJson("/api/v1/admin/content/labs/{$lab['id']}/draft", ['lock_version' => 0] + $def)->assertUnprocessable()->assertJsonValidationErrors(['scenario.rules.0.port']);

        $def['scenario']['rules'][0]['port'] = 22;
        $saved = $this->putJson("/api/v1/admin/content/labs/{$lab['id']}/draft", ['lock_version' => 0] + $def)->assertOk()->json('data');
        expect($saved['lock_version'])->toBe(1)->and($saved['has_draft'])->toBeTrue()->and($saved['owned'])->toBeTrue();

        $this->putJson("/api/v1/admin/content/labs/{$lab['id']}/draft", ['lock_version' => 0] + $def)->assertStatus(409);
        // The live lab and what learners get stay as they were.
        expect(Lab::query()->where('slug', 'lock-down-a-web-server')->first()->scenario['rules'][0]['port'])->toBe(22);
    });

    it('checks a definition without saving, replaying every solution', function () {
        labAdmin();
        $lab = labAdminData('lock-down-a-web-server');
        $def = $lab['definition'];
        $def['solutions']['solutions'][0]['actions'] = [['type' => 'set_default', 'policy' => 'deny']];

        $report = $this->postJson("/api/v1/admin/content/labs/{$lab['id']}/check", $def)->assertOk()->json('data');
        expect($report['valid'])->toBeFalse()->and($report['errors'])->toHaveKey('solutions.solutions.0');
        expect($this->postJson("/api/v1/admin/content/labs/{$lab['id']}/check")->json('data.valid'))->toBeTrue();
    });

    it('will not publish a lab whose solution fails, and says where', function () {
        labAdmin();
        $saved = labEdit('lock-down-a-web-server', function (array $def) {
            $def['solutions']['solutions'][0]['actions'] = [['type' => 'set_default', 'policy' => 'deny']];

            return $def;
        });

        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/publish", ['lock_version' => $saved['lock_version']])->assertUnprocessable()->assertJsonValidationErrors(['solutions.solutions.0']);
    });

    it('publishes with a revision, an audit entry that holds no answers, and restores an earlier version into the draft', function () {
        labAdmin();
        $saved = labEdit('inspect-a-shop-session', function (array $def) {
            $def['title'] = 'Inspect a shop session, again';

            return $def;
        });
        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/publish", ['lock_version' => $saved['lock_version'], 'note' => 'Wording'])->assertOk()
            ->assertJsonPath('data.has_draft', false)->assertJsonPath('data.definition.title', 'Inspect a shop session, again');

        $audit = AdminAuditLog::query()->where('action', 'lab.published')->firstOrFail();
        expect(json_encode($audit->changes))->not->toContain('minh.tran')->not->toContain('HttpOnly')
            ->and(json_encode(AdminAuditLog::query()->get()->pluck('changes')))->not->toContain('203.0.113');
        $this->getJson("/api/v1/admin/content/labs/{$saved['id']}/revisions")->assertOk()->assertJsonPath('data.0.version', 1)->assertJsonPath('data.0.note', 'Wording');

        // An edited title is not breaking: the scenario version stays.
        expect(Lab::query()->where('slug', 'inspect-a-shop-session')->value('scenario_version'))->toBe(1);

        labEdit('inspect-a-shop-session', fn (array $def) => [...$def, 'title' => 'Something else']);
        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/revisions/1/restore")->assertOk()->assertJsonPath('data.definition.title', 'Inspect a shop session, again');
    });

    it('discards a draft, but not the only copy of a lab that was never published', function () {
        labAdmin();
        $saved = labEdit('inspect-a-shop-session', fn (array $def) => [...$def, 'title' => 'Discard me']);
        $this->deleteJson("/api/v1/admin/content/labs/{$saved['id']}/draft")->assertOk()->assertJsonPath('data.definition.title', 'Inspect an online shop\'s session');

        $new = $this->postJson('/api/v1/admin/content/labs', ['slug' => 'never-published', 'engine' => 'linux-shell', 'title' => 'N', 'domain' => 'linux'])->json('data');
        $this->deleteJson("/api/v1/admin/content/labs/{$new['id']}/draft")->assertUnprocessable();
    });

    it('archives and unarchives, and orders every lab', function () {
        labAdmin();
        $id = Lab::query()->where('slug', 'lock-down-a-web-server')->value('id');
        $this->postJson("/api/v1/admin/content/labs/{$id}/archive")->assertOk()->assertJsonPath('data.is_archived', true);
        labLearner();
        $this->getJson('/api/v1/labs/lock-down-a-web-server')->assertNotFound();
        labAdmin();
        $this->deleteJson("/api/v1/admin/content/labs/{$id}/archive")->assertOk()->assertJsonPath('data.is_archived', false);

        $ids = Lab::query()->orderByDesc('position')->pluck('id')->all();
        $this->putJson('/api/v1/admin/content/labs/order', ['ids' => $ids])->assertOk();
        expect(Lab::query()->orderBy('position')->pluck('id')->all())->toBe($ids);
        $this->putJson('/api/v1/admin/content/labs/order', ['ids' => [$ids[0]]])->assertUnprocessable();
    });
});

describe('answers stay on the server', function () {
    it('never sends solutions, decoys or draft text to learners', function () {
        labAdmin();
        labEdit('inspect-a-shop-session', fn (array $def) => [...$def, 'briefing' => 'DRAFT-ONLY-BRIEFING']);

        labLearner();
        $texts = [
            $this->getJson('/api/v1/labs')->getContent(),
            $this->getJson('/api/v1/labs/inspect-a-shop-session')->getContent(),
            $this->postJson('/api/v1/labs/inspect-a-shop-session/attempts')->getContent(),
        ];
        foreach ($texts as $json) {
            expect($json)->not->toContain('"solutions"')->not->toContain('"decoys"')->not->toContain('DRAFT-ONLY-BRIEFING')->not->toContain('Only look at your own order');
        }
    });

    it('keeps the lab model from serializing its scenario or solutions', function () {
        $lab = Lab::query()->firstOrFail();

        expect(array_keys($lab->toArray()))->not->toContain('scenario')->not->toContain('solutions');
    });
});

describe('editing a lab learners are in', function () {
    beforeEach(function () {
        labLearner();
        $this->attempt = $this->postJson('/api/v1/labs/lock-down-a-web-server/attempts')->assertCreated()->json('data.attempt');
        $this->postJson("/api/v1/lab-attempts/{$this->attempt['id']}/actions", ['version' => $this->attempt['version'], 'action' => ['type' => 'test', 'packet' => ['proto' => 'tcp', 'port' => 443, 'source' => '198.51.100.7']]])->assertOk();
        // A second learner who finished it once.
        $this->finisher = User::factory()->create();
        LabAttempt::query()->create(['user_id' => $this->finisher->id, 'lab_id' => Lab::query()->where('slug', 'lock-down-a-web-server')->value('id'), 'state' => ['engine' => ['rules' => [], 'default' => 'deny', 'tests' => []], 'answers' => (object) []], 'checkpoints_passed' => ['https-open'], 'action_count' => 9, 'completed_at' => now()]);
    });

    it('does not restart anyone for a change of words', function () {
        labAdmin();
        $saved = labEdit('lock-down-a-web-server', function (array $def) {
            $def['title'] = 'Lock down a web server';
            $def['scenario']['checkpoints'][2]['hint'] = 'A new hint.';
            $def['scenario']['checkpoints'][0]['title'] = 'A new title.';

            return $def;
        });
        expect($saved['impact']['breaking'])->toBeFalse();
        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/publish", ['lock_version' => $saved['lock_version']])->assertOk();

        labLearner();
        $shown = $this->getJson('/api/v1/labs/lock-down-a-web-server')->json('data');
        expect($shown['attempt']['restarted'])->toBeFalse()->and($shown['attempt']['version'])->toBe(2);
        expect(Lab::query()->where('slug', 'lock-down-a-web-server')->value('scenario_version'))->toBe(1);
    });

    it('shows the count and asks to confirm a breaking change, then restarts attempts and keeps completion', function () {
        labAdmin();
        $saved = labEdit('lock-down-a-web-server', function (array $def) {
            $def['scenario']['server']['name'] = 'web-02';

            return $def;
        });
        expect($saved['impact'])->toBe(['breaking' => true, 'in_progress' => 1, 'completed' => 1]);

        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/publish", ['lock_version' => $saved['lock_version']])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirm_restart']);
        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/publish", ['lock_version' => $saved['lock_version'], 'confirm_restart' => true])->assertOk();
        expect(Lab::query()->where('slug', 'lock-down-a-web-server')->value('scenario_version'))->toBe(2);

        Event::fake([LabCompleted::class]);
        labLearner();
        $shown = $this->getJson('/api/v1/labs/lock-down-a-web-server')->assertOk()->json('data');
        expect($shown['attempt']['restarted'])->toBeTrue()->and($shown['scenario']['server']['name'])->toBe('web-02')
            ->and($shown['attempt']['state']['tests'])->toBeEmpty();

        // A stale tab is told to reload, then the notice goes once the learner acts again.
        $this->postJson("/api/v1/lab-attempts/{$this->attempt['id']}/actions", ['version' => $this->attempt['version'] + 1, 'action' => ['type' => 'test', 'packet' => ['proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.25']]])->assertStatus(409);
        $version = $shown['attempt']['version'];
        $this->postJson("/api/v1/lab-attempts/{$this->attempt['id']}/actions", ['version' => $version, 'action' => ['type' => 'test', 'packet' => ['proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.25']]])->assertOk()->assertJsonPath('data.attempt.restarted', false);

        // The learner who finished before still has it, and nothing fired again.
        Auth::forgetGuards();
        $this->actingAs($this->finisher, 'web');
        $theirs = $this->getJson('/api/v1/labs/lock-down-a-web-server')->json('data');
        expect($theirs['progress'])->toMatchArray(['completed' => true, 'passed' => 0])->and($theirs['attempt']['restarted'])->toBeTrue();
        Event::assertNotDispatched(LabCompleted::class);
    });
});

describe('the admin preview', function () {
    it('plays a draft to completion without touching learner data', function () {
        Event::fake([LabCompleted::class]);
        labAdmin();
        $saved = labEdit('lock-down-a-web-server', fn (array $def) => [...$def, 'title' => 'Draft only title']);
        $counts = fn () => [LabAttempt::query()->count(), LearningActivity::query()->count(), DB::table('notifications')->count()];
        $before = $counts();

        $open = $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/preview")->assertOk()->json('data');
        expect($open['title'])->toBe('Draft only title')->and($open['attempt']['version'])->toBeInt();

        $version = $open['attempt']['version'];
        $solution = $saved['definition']['solutions']['solutions'][0]['actions'];
        foreach ($solution as $action) {
            $r = $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/preview/actions", ['version' => $version, 'action' => $action])->assertOk();
            $version = $r->json('data.attempt.version');
        }
        expect(collect($r->json('data.checkpoints'))->every(fn ($c) => $c['passed']))->toBeTrue()->and($r->json('data.attempt.completed_at'))->not->toBeNull();

        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/preview/actions", ['version' => $version - 1, 'action' => $solution[0]])->assertStatus(409);
        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/preview/reset")->assertOk();

        expect($counts())->toBe($before);
        Event::assertNotDispatched(LabCompleted::class);
    });

    it('starts over when the draft changes', function () {
        labAdmin();
        $saved = labEdit('lock-down-a-web-server', fn (array $def) => $def);
        $open = $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/preview")->json('data');
        $action = ['type' => 'set_default', 'policy' => 'deny'];
        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/preview/actions", ['version' => $open['attempt']['version'], 'action' => $action])->assertOk();

        $again = labEdit('lock-down-a-web-server', fn (array $def) => [...$def, 'title' => 'Changed']);
        $this->postJson("/api/v1/admin/content/labs/{$again['id']}/preview/actions", ['version' => $open['attempt']['version'] + 1, 'action' => $action])->assertStatus(409);
        expect($this->postJson("/api/v1/admin/content/labs/{$again['id']}/preview")->json('data.attempt.restarted'))->toBeFalse();
    });

    it('keeps a learner from previewing', function () {
        labLearner();
        $this->postJson('/api/v1/admin/content/labs/'.Lab::query()->value('id').'/preview')->assertForbidden();
    });
});

describe('who owns a lab: the files or the database', function () {
    it('skips a lab an admin edited, and never archives one made in the admin', function () {
        labAdmin();
        labEdit('lock-down-a-web-server', fn (array $def) => [...$def, 'title' => 'Admin title']);
        $made = $this->postJson('/api/v1/admin/content/labs', ['slug' => 'admin-made', 'engine' => 'linux-shell', 'title' => 'Made', 'domain' => 'linux'])->json('data');
        $this->postJson("/api/v1/admin/content/labs/{$made['id']}/publish", ['lock_version' => 1])->assertOk();

        $report = app(LabLoader::class)->load(LabFiles::read()['labs']);
        expect($report['skipped'])->toBe(['lock-down-a-web-server'])->and($report['archived'])->toBe([]);
        // The admin's draft is untouched, and so are the live row and the made lab.
        expect(Lab::query()->where('slug', 'admin-made')->value('is_archived'))->toBeFalse();

        $forced = app(LabLoader::class)->load(LabFiles::read()['labs'], force: true);
        expect($forced['skipped'])->toBe([])->and(Lab::query()->where('slug', 'lock-down-a-web-server')->value('title'))->toBe('Lock down a web server\'s firewall');
    });

    it('does not hand a lab back to the files when its editor account is deleted', function () {
        labAdmin();
        $made = $this->postJson('/api/v1/admin/content/labs', ['slug' => 'orphaned', 'engine' => 'linux-shell', 'title' => 'Made', 'domain' => 'linux'])->json('data');
        $this->postJson("/api/v1/admin/content/labs/{$made['id']}/publish", ['lock_version' => 1])->assertOk();
        Lab::query()->whereKey($made['id'])->update(['updated_by' => null]);

        expect(app(LabLoader::class)->load(LabFiles::read()['labs'])['archived'])->toBe([]);
    });

    it('keeps the order an admin set when the files are loaded again, and restarts attempts when the files change a scenario', function () {
        $ids = Lab::query()->orderByDesc('position')->pluck('id')->all();
        labAdmin();
        $this->putJson('/api/v1/admin/content/labs/order', ['ids' => $ids])->assertOk();
        app(LabLoader::class)->load(LabFiles::read()['labs']);
        expect(Lab::query()->orderBy('position')->pluck('id')->all())->toBe($ids);

        $file = LabFiles::read()['labs'];
        $file['lock-down-a-web-server']['scenario']['server']['name'] = 'web-09';
        app(LabLoader::class)->load($file);
        expect(Lab::query()->where('slug', 'lock-down-a-web-server')->value('scenario_version'))->toBe(2);
    });

    it('lets the per-lab file win over labs.php and says so', function () {
        $dir = sys_get_temp_dir().'/labfiles-'.bin2hex(random_bytes(4));
        mkdir("{$dir}/labs", 0777, true);
        $def = labStarter('linux-shell', ['title' => 'From the per-lab file']);
        file_put_contents("{$dir}/labs.php", '<?php return '.var_export(['same-slug' => [...$def, 'title' => 'From labs.php'], 'only-here' => $def], true).';');
        file_put_contents("{$dir}/labs/same-slug.php", '<?php return '.var_export(['same-slug' => $def], true).';');

        $files = LabFiles::read($dir);
        expect($files['shadowed'])->toBe(['same-slug'])->and($files['labs']['same-slug']['title'])->toBe('From the per-lab file')->and(array_keys($files['labs']))->toBe(['same-slug', 'only-here']);

        file_put_contents("{$dir}/labs/wrong-name.php", '<?php return '.var_export(['other' => $def], true).';');
        expect(fn () => LabFiles::read($dir))->toThrow(InvalidArgumentException::class, 'must return an array with exactly one entry, keyed "wrong-name"');
        array_map('unlink', glob("{$dir}/labs/*.php"));
        array_map('unlink', glob("{$dir}/*.php"));
        rmdir("{$dir}/labs");
        rmdir($dir);
    });

    it('exports a lab to labs/<slug>.php, which the loader reads back unchanged', function () {
        $dir = sys_get_temp_dir().'/labexport-'.bin2hex(random_bytes(4));
        mkdir("{$dir}/labs", 0777, true);
        $path = "{$dir}/labs/export-roundtrip.php";
        labAdmin();
        $made = $this->postJson('/api/v1/admin/content/labs', ['slug' => 'export-roundtrip', 'engine' => 'http-inspector', 'title' => 'Round trip', 'domain' => 'web-security'])->json('data');
        $this->postJson("/api/v1/admin/content/labs/{$made['id']}/publish", ['lock_version' => 1])->assertOk();
        $original = LabDefinition::fromLab(Lab::query()->findOrFail($made['id']));

        try {
            $this->artisan('content:export', ['course' => 'export-roundtrip', '--lab' => true, '--path' => $path])->assertSuccessful();
            $files = LabFiles::read($dir);
            expect($files['labs']['export-roundtrip'])->toEqual($original);

            // A fresh database reads the file as a file-owned lab.
            Lab::query()->where('slug', 'export-roundtrip')->delete();
            expect(app(LabLoader::class)->load($files['labs'])['labs'])->toBe(1)
                ->and(Lab::query()->where('slug', 'export-roundtrip')->value('updated_by'))->toBeNull();
        } finally {
            @unlink($path);
            @rmdir("{$dir}/labs");
            @rmdir($dir);
        }
    });
});

describe('the health check', function () {
    it('passes on the seeded labs and fails when a stored lab can no longer be solved', function () {
        $this->artisan('content:check-labs')->assertSuccessful();

        $lab = Lab::query()->where('slug', 'inspect-a-shop-session')->firstOrFail();
        $solutions = $lab->solutions;
        $solutions['solutions'][0]['actions'] = [];
        $lab->forceFill(['solutions' => $solutions])->save();

        $this->artisan('content:check-labs')->assertFailed();
        $this->artisan('content:check-labs', ['--warn-only' => true])->assertSuccessful();
    });
});

describe('what the learner is shown (F7 Part B)', function () {
    it('flags conditions that are true at the start and leaves them out of the progress counts', function () {
        labLearner();
        $lab = $this->getJson('/api/v1/labs/lock-down-a-web-server')->assertOk()->json('data');

        expect(collect($lab['checkpoints'])->where('starts_true', true)->pluck('id')->all())->toBe(['https-open', 'http-redirect'])
            ->and($lab['progress'])->toMatchArray(['passed' => 0, 'total' => 3, 'holds' => 2]);

        // A lab that starts with nothing true has no conditions to keep.
        $shell = $this->getJson('/api/v1/labs/investigate-a-suspicious-login')->json('data');
        expect(collect($shell['checkpoints'])->where('starts_true', true))->toBeEmpty()->and($shell['progress']['holds'])->toBe(0);
    });

    it('tells a learner that the lab started over until they have seen it or acted', function () {
        labLearner();
        $attempt = $this->postJson('/api/v1/labs/lock-down-a-web-server/attempts')->assertCreated()->json('data.attempt');
        labAdmin();
        $saved = labEdit('lock-down-a-web-server', function (array $def) {
            $def['scenario']['server']['name'] = 'web-03';

            return $def;
        });
        $this->postJson("/api/v1/admin/content/labs/{$saved['id']}/publish", ['lock_version' => $saved['lock_version'], 'confirm_restart' => true])->assertOk();

        labLearner();
        // Reading the lab twice (a reload, a second tab) shows the notice twice: it is not used up by being fetched.
        expect($this->getJson('/api/v1/labs/lock-down-a-web-server')->json('data.attempt.restarted'))->toBeTrue()
            ->and($this->getJson('/api/v1/labs/lock-down-a-web-server')->json('data.attempt.restarted'))->toBeTrue();

        $this->postJson("/api/v1/lab-attempts/{$attempt['id']}/notice-seen")->assertOk();
        expect($this->getJson('/api/v1/labs/lock-down-a-web-server')->json('data.attempt.restarted'))->toBeFalse();
        // Someone else's attempt is not reachable.
        Auth::forgetGuards();
        $this->actingAs($this->admin, 'web');
        $this->postJson("/api/v1/lab-attempts/{$attempt['id']}/notice-seen")->assertNotFound();
    });

    it('lists the domains, and says when a lab is in both files', function () {
        labAdmin();
        $json = $this->getJson('/api/v1/admin/content/labs')->assertOk()->json();

        expect(collect($json['meta']['domains'])->pluck('slug')->all())->toContain('linux')->and(collect($json['data'])->every(fn ($l) => $l['shadowed'] === false))->toBeTrue();
    });
});
