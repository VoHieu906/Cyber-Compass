<?php

namespace App\Modules\Admin\Console;

use App\Models\Course;
use App\Models\CourseGraph;
use App\Models\Lesson;
use App\Models\LessonArchitecture;
use App\Modules\Content\CourseMap\MapSchemas;
use App\Modules\Content\Services\ContentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A small test course map on Applied Cryptography for the Playwright suite and browser checks
 * (F3). It is a fixture, not seed content: real maps arrive with F3 Part C. `install` writes it
 * only where nothing exists yet and `remove` deletes only what equals the fixture, so a real map
 * is never touched. Refuses to run outside local and testing environments.
 *
 *   php artisan cybercompass:e2e-course-map install|remove
 */
final class E2eCourseMapCommand extends Command
{
    public const COURSE = 'applied-cryptography';

    protected $signature = 'cybercompass:e2e-course-map {action : install or remove}';

    protected $description = 'Install or remove the test course map (local and testing only)';

    /** @return array{graph: array<string, mixed>, architectures: array<string, array<string, mixed>>} */
    public static function fixture(): array
    {
        return [
            'graph' => [
                'version' => 1,
                'problem' => 'Keep data secret and unaltered, at rest and on the way, from people who should not read or change it.',
                'layout' => 'auto',
                'edges' => [
                    ['from' => 'hashing-and-passwords', 'to' => 'symmetric-asymmetric-encryption', 'type' => 'contrasts-with', 'label' => 'one-way or keyed'],
                    ['from' => 'symmetric-asymmetric-encryption', 'to' => 'tls-overview', 'type' => 'feeds-into'],
                ],
            ],
            'architectures' => [
                'hashing-and-passwords' => [
                    'version' => 1,
                    'problem' => 'Store passwords so that a stolen database does not give them away.',
                    'shape' => 'pipeline',
                    'nodes' => [
                        ['id' => 'password', 'label' => 'Password typed at sign-up', 'section' => 'what-a-hash-does', 'note' => 'The only moment the plain password exists on the server.'],
                        ['id' => 'salt', 'label' => 'Unique salt', 'section' => 'passwords-need-slow-hashes', 'note' => 'Random per user, so equal passwords give different digests.'],
                        ['id' => 'slow', 'label' => 'Slow hash (Argon2, bcrypt)', 'section' => 'passwords-need-slow-hashes', 'note' => 'Deliberately expensive, so guessing at scale is too slow to pay off.'],
                        ['id' => 'digest', 'label' => 'Stored digest', 'section' => 'hashing-is-not-encryption', 'note' => 'One-way: there is no key that turns it back into the password.', 'lesson' => 'symmetric-asymmetric-encryption'],
                    ],
                    'edges' => [],
                ],
                'symmetric-asymmetric-encryption' => [
                    'version' => 1,
                    'problem' => 'Share secrets with someone you have never met, quickly enough to use every day.',
                    'shape' => 'matrix',
                    'rows' => ['Keys', 'Speed', 'Best for'],
                    'cols' => ['Symmetric', 'Asymmetric'],
                    'nodes' => [
                        ['id' => 'sym-keys', 'label' => 'One shared key', 'cell' => [0, 0], 'section' => 'symmetric-one-shared-key'],
                        ['id' => 'asym-keys', 'label' => 'Public and private key pair', 'cell' => [0, 1], 'section' => 'asymmetric-a-key-pair'],
                        ['id' => 'sym-speed', 'label' => 'Fast, for bulk data', 'cell' => [1, 0]],
                        ['id' => 'asym-speed', 'label' => 'Slow, for small data', 'cell' => [1, 1]],
                        ['id' => 'sym-use', 'label' => 'Encrypting the session', 'cell' => [2, 0], 'lesson' => 'tls-overview'],
                        ['id' => 'asym-use', 'label' => 'Agreeing keys and signing', 'cell' => [2, 1], 'section' => 'signatures'],
                    ],
                    'edges' => [],
                ],
                'tls-overview' => [
                    'version' => 1,
                    'problem' => 'Talk privately to the right server over a network anyone can watch.',
                    'shape' => 'layers',
                    'nodes' => [
                        ['id' => 'app', 'label' => 'HTTP: the page you asked for'],
                        ['id' => 'record', 'label' => 'TLS record: encrypted and authenticated', 'section' => 'the-tls-13-handshake-simplified'],
                        ['id' => 'trust', 'label' => 'Certificate chain: who the server is', 'section' => 'certificates-and-trust'],
                        ['id' => 'tcp', 'label' => 'TCP: reliable delivery'],
                    ],
                    'edges' => [],
                ],
            ],
        ];
    }

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('cybercompass:e2e-course-map runs only in local and testing environments.');

            return self::FAILURE;
        }
        $course = Course::query()->where('slug', self::COURSE)->firstOrFail();
        $lessons = Lesson::query()->whereIn('slug', array_keys(self::fixture()['architectures']))->with('sections:id,lesson_id,uuid')->get()->keyBy('slug');
        $wanted = $this->resolved($lessons->all());

        return match ($this->argument('action')) {
            'install' => $this->install($course, $lessons->all(), $wanted),
            'remove' => $this->remove($course, $lessons->all(), $wanted),
            default => $this->fail('The action is install or remove.'),
        };
    }

    /**
     * The fixture with section keys turned into this database's section UUIDs, validated.
     *
     * @param  array<string, Lesson>  $lessons
     * @return array{graph: array<string, mixed>, architectures: array<string, array<string, mixed>>}
     */
    private function resolved(array $lessons): array
    {
        $fixture = self::fixture();
        foreach ($fixture['architectures'] as $slug => &$arch) {
            foreach ($arch['nodes'] as &$node) {
                if (isset($node['section'])) {
                    $node['section'] = ContentImporter::sectionUuid($slug, $node['section']);
                }
            }
            unset($node);
            [$errors, $arch] = MapSchemas::architecture($arch, $lessons[$slug]->sections->pluck('uuid')->all(), $slug);
            if ($errors) {
                throw new \RuntimeException('The test course map no longer fits the lessons: '.json_encode($errors));
            }
        }
        unset($arch);

        return $fixture;
    }

    /** @param  array<string, Lesson>  $lessons */
    private function install(Course $course, array $lessons, array $wanted): int
    {
        DB::transaction(function () use ($course, $lessons, $wanted) {
            if (! CourseGraph::query()->where('course_id', $course->id)->exists()) {
                CourseGraph::query()->create(['course_id' => $course->id, 'graph' => $wanted['graph']]);
            }
            foreach ($wanted['architectures'] as $slug => $arch) {
                LessonArchitecture::query()->firstOrCreate(['lesson_id' => $lessons[$slug]->id], ['architecture' => $arch]);
            }
        });
        $this->line(json_encode(['installed' => self::COURSE]));

        return self::SUCCESS;
    }

    /** @param  array<string, Lesson>  $lessons */
    private function remove(Course $course, array $lessons, array $wanted): int
    {
        DB::transaction(function () use ($course, $lessons, $wanted) {
            $graph = CourseGraph::query()->where('course_id', $course->id)->first();
            if ($graph && $graph->graph == $wanted['graph']) {
                $graph->delete();
            }
            foreach ($wanted['architectures'] as $slug => $arch) {
                $row = LessonArchitecture::query()->where('lesson_id', $lessons[$slug]->id)->first();
                if ($row && $row->architecture == $arch) {
                    $row->delete();
                }
            }
        });
        $this->line(json_encode(['removed' => self::COURSE]));

        return self::SUCCESS;
    }
}
