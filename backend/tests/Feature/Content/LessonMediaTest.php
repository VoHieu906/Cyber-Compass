<?php

use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('cybercompass.uploads_disk'));
    $this->admin = User::factory()->admin()->create();
});

it('re-encodes an upload to WebP under a random name, at most 1600 px wide', function () {
    $response = $this->actingAs($this->admin, 'web')->post('/api/v1/admin/content/media', [
        'image' => UploadedFile::fake()->image('screenshot.png', 2400, 1200),
        'alt' => 'Wireshark capture of a TLS handshake',
    ], ['Accept' => 'application/json'])->assertCreated();

    // Seeded content may hold images from content files (D56); uploads have no source.
    $media = LessonMedia::query()->whereNull('source')->sole();
    expect($response->json('data.id'))->toBe($media->uuid)
        ->and($media->path)->toBe("lesson-media/{$media->uuid}.webp")
        ->and([$media->width, $media->height])->toBe([1600, 800])
        ->and(Storage::disk(config('cybercompass.uploads_disk'))->mimeType($media->path))->toBe('image/webp');
    $this->assertDatabaseHas('admin_audit_logs', ['action' => 'media.uploaded', 'subject_type' => 'media']);
});

it('requires alt text and refuses SVG', function () {
    $this->actingAs($this->admin, 'web')->post('/api/v1/admin/content/media', [
        'image' => UploadedFile::fake()->image('shot.png', 400, 300),
    ], ['Accept' => 'application/json'])->assertJsonValidationErrors('alt');

    $svg = UploadedFile::fake()->createWithContent('diagram.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $this->actingAs($this->admin, 'web')->post('/api/v1/admin/content/media', ['image' => $svg, 'alt' => 'A diagram'], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors('image');

    expect(LessonMedia::query()->whereNull('source')->count())->toBe(0);
});

it('puts an uploaded image in a lesson, resolved for learners at read time', function () {
    $upload = $this->actingAs($this->admin, 'web')->post('/api/v1/admin/content/media', [
        'image' => UploadedFile::fake()->image('shot.jpg', 800, 600),
        'alt' => 'A terminal running sha256sum',
    ], ['Accept' => 'application/json'])->json('data');

    $lesson = Lesson::query()->where('slug', 'hashing-and-passwords')->firstOrFail();
    $data = $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/content/lessons/{$lesson->id}")->json('data');
    $sections = [...$data['working_copy']['sections'], ['type' => 'media', 'heading' => null, 'payload' => ['media' => $upload['id'], 'caption' => 'Hashing a file.']]];
    $saved = $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$lesson->id}/draft", [...$data['working_copy'], 'sections' => $sections, 'lock_version' => 0])
        ->assertOk()
        ->assertJsonPath('data.preview_sections.'.(count($sections) - 1).'.payload.alt', 'A terminal running sha256sum')
        ->json('data');
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$lesson->id}/publish", ['lock_version' => $saved['lock_version']])->assertOk();

    $learner = User::factory()->create();
    $block = collect($this->actingAs($learner, 'web')->getJson('/api/v1/lessons/hashing-and-passwords')->json('data.sections'))->last();
    expect($block['type'])->toBe('media')
        ->and($block['payload']['url'])->toEndWith("lesson-media/{$upload['id']}.webp")
        ->and($block['payload'])->toMatchArray(['caption' => 'Hashing a file.', 'width' => 800, 'height' => 600]);
});

it('rejects a media block whose image does not exist', function () {
    $lesson = Lesson::query()->where('slug', 'hashing-and-passwords')->firstOrFail();
    $data = $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/content/lessons/{$lesson->id}")->json('data');

    $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$lesson->id}/draft", [
        ...$data['working_copy'],
        'sections' => [['type' => 'media', 'heading' => null, 'payload' => ['media' => '3f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30']]],
        'lock_version' => 0,
    ])->assertJsonValidationErrors('sections.0.payload.media');
});
