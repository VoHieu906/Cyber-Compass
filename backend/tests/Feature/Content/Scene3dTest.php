<?php

use App\Models\Lesson;
use App\Models\User;
use App\Modules\Content\Blocks\BlockRegistry;

/*
| 3D blocks (docs/SANDBOXED_3D.md). The server stores and validates them; they only ever run
| in the client sandbox. These tests cover the server's part: contracts, cross-references,
| and the operator switch for scripted blocks.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->learner = User::factory()->create();
    $this->lesson = Lesson::query()->where('slug', 'hashing-and-passwords')->firstOrFail();
});

function exampleOf(string $type): array
{
    return BlockRegistry::default()->get($type)->example();
}

/** Saves the given sections as the lesson's draft and returns the response. */
function saveSections(array $sections)
{
    $data = test()->actingAs(test()->admin, 'web')->getJson('/api/v1/admin/content/lessons/'.test()->lesson->id)->json('data');

    return test()->actingAs(test()->admin, 'web')->putJson('/api/v1/admin/content/lessons/'.test()->lesson->id.'/draft', [
        ...$data['working_copy'], 'sections' => $sections, 'lock_version' => $data['lock_version'],
    ]);
}

it('publishes a scene and serves it to learners as data', function () {
    $scene = exampleOf('scene-3d');
    $saved = saveSections([['type' => 'scene-3d', 'heading' => 'See it move', 'payload' => $scene]])->assertOk()->json('data');
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/publish", ['lock_version' => $saved['lock_version']])->assertOk();

    Auth::forgetGuards();
    $block = $this->actingAs($this->learner, 'web')->getJson('/api/v1/lessons/hashing-and-passwords')->json('data.sections.0');

    expect($block['type'])->toBe('scene-3d')
        ->and($block['payload']['objects'])->toHaveCount(count($scene['objects']))
        ->and($block['payload']['aspect'])->toBe('16:9');
});

it('reports scene cross-reference errors at the exact path', function () {
    $scene = exampleOf('scene-3d');
    $scene['objects'][1]['parent'] = 'ghost';
    $scene['links'][0]['to'] = 'nowhere';
    $scene['steps'][1]['highlight'] = ['laptop', 'missing'];

    saveSections([['type' => 'scene-3d', 'heading' => null, 'payload' => $scene]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'sections.0.payload.objects.1.parent',
            'sections.0.payload.links.0.to',
            'sections.0.payload.steps.1.highlight.1',
        ]);
});

it('refuses budgets beyond the limits', function () {
    $scene = exampleOf('scene-3d');
    $scene['objects'] = array_map(fn ($i) => ['id' => "o{$i}", 'shape' => 'box'], range(1, 151));
    unset($scene['links'], $scene['steps']);

    saveSections([['type' => 'scene-3d', 'heading' => null, 'payload' => $scene]])
        ->assertJsonValidationErrors(['sections.0.payload.objects']);
});

it('stores 3D script code exactly as written, and describes the block types', function () {
    $script = exampleOf('script-3d');

    saveSections([['type' => 'script-3d', 'heading' => null, 'payload' => $script]])
        ->assertOk()
        ->assertJsonPath('data.working_copy.sections.0.payload.code', $script['code']);

    $types = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/content/block-types')->json('data'))->keyBy('key');
    expect($types['scene-3d']['editor'])->toBe('scene')
        ->and($types['script-3d']['editor'])->toBe('script');
});

it('lets operators switch 3D scripts off: no new ones, and no code sent to readers', function () {
    $script = exampleOf('script-3d');
    $saved = saveSections([['type' => 'script-3d', 'heading' => null, 'payload' => $script]])->json('data');
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/publish", ['lock_version' => $saved['lock_version']])->assertOk();

    config(['cybercompass.content.scripted_3d' => false]);

    saveSections([['type' => 'script-3d', 'heading' => null, 'payload' => $script]])
        ->assertJsonValidationErrors(['sections.0.payload.code']);

    Auth::forgetGuards();
    $block = $this->actingAs($this->learner, 'web')->getJson('/api/v1/lessons/hashing-and-passwords')->json('data.sections.0');
    expect($block['payload'])->not->toHaveKey('code')
        ->and($block['payload']['disabled'])->toBeTrue()
        ->and($block['payload']['description'])->toBe($script['description']);
});
