<?php

use App\Models\AdminAuditLog;
use App\Models\ContentTransfer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\User;
use App\Modules\Content\Packages\ImportPackage;
use App\Modules\Content\Packages\Jobs\ValidateTransfer;
use App\Modules\Content\Packages\PackagePlanner;
use App\Modules\Content\Packages\PackageReader;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| Content package transfers (F6, D70): upload in parts, validate on the worker into a preview,
| apply on the worker into drafts, export as a zip. Every step reads the file from the transfers
| disk into a folder of its own, so the api and the worker never share a temporary file.
| The queue is `sync` in tests, so each job runs inside the request that queues it.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Storage::fake('local');
    Storage::fake(config('cybercompass.uploads_disk'));
    config(['cybercompass.content_packages.disk' => 'local', 'cybercompass.content_packages.part_bytes' => 4096]);
    File::deleteDirectory(PackageReader::workDir());
});

function asTransferAdmin(?User $user = null)
{
    Auth::forgetGuards();

    return test()->actingAs($user ?? test()->admin, 'web');
}

function sectionsPackage(string $text = 'From a transfer.', array $base = []): array
{
    return [
        'format' => 'cybercompass.content', 'format_version' => 1, 'kind' => 'lesson-sections', 'generator' => 'Pest',
        'target' => ['lesson' => 'osi-model'], 'base' => (object) $base,
        'operations' => [['op' => 'append', 'section' => ['key' => 'from-a-transfer', 'heading' => 'From a transfer', 'type' => 'prose', 'payload' => ['text' => $text]]]],
    ];
}

/** Uploads bytes the way the admin UI does locally: start, every part, complete. Returns the transfer as JSON. */
function upload(string $bytes, string $name = 'pkg.ccpkg.json'): array
{
    $start = asTransferAdmin()->postJson('/api/v1/admin/content/transfers', ['file_name' => $name, 'size' => strlen($bytes)])->assertCreated()->json('data');
    expect($start['upload']['mode'])->toBe('chunked');
    foreach ($start['upload']['parts'] as $part) {
        $chunk = substr($bytes, ($part['number'] - 1) * $start['upload']['part_bytes'], $start['upload']['part_bytes']);
        asTransferAdmin()->call('PUT', "/api/v1/admin/content/transfers/{$start['id']}/parts/{$part['number']}", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $chunk)->assertOk();
    }

    return asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$start['id']}/complete")->assertOk()->json('data');
}

function workFolders(): array
{
    return is_dir(PackageReader::workDir()) ? File::directories(PackageReader::workDir()) : [];
}

function zipBytes(array $entries): string
{
    $file = tempnam(sys_get_temp_dir(), 'pkg');
    $zip = new ZipArchive;
    $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $bytes) {
        $zip->addFromString($name, $bytes);
    }
    $zip->close();
    $bytes = file_get_contents($file);
    unlink($file);

    return $bytes;
}

function smallPng(): string
{
    $image = imagecreatetruecolor(32, 24);
    imagefilledrectangle($image, 0, 0, 31, 23, imagecolorallocate($image, 200, 80, 30));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

it('uploads in parts, previews, applies into the draft and never applies twice', function () {
    $json = json_encode(sectionsPackage(str_repeat('Long text. ', 900)));
    $transfer = upload($json);

    expect($transfer['status'])->toBe('ready')
        ->and($transfer['report']['valid'])->toBeTrue()
        ->and(collect($transfer['report']['documents'][0]['changes'])->contains(fn ($c) => $c['change'] === 'added' && $c['key'] === 'from-a-transfer'))->toBeTrue()
        ->and($transfer['report'])->not->toHaveKey('locks')
        ->and(ContentTransfer::query()->find($transfer['id'])->parts)->toBeGreaterThan(2)
        ->and(Lesson::query()->where('slug', 'osi-model')->first()->draft)->toBeNull()
        ->and(workFolders())->toBe([]);

    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$transfer['id']}/apply")->assertAccepted()->assertJsonPath('data.status', 'applied');
    $draft = Lesson::query()->where('slug', 'osi-model')->first()->draft;
    expect(collect($draft->snapshot['sections'])->last()['heading'])->toBe('From a transfer');

    // A double click or a retried request starts nothing new.
    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$transfer['id']}/apply")->assertAccepted()->assertJsonPath('data.status', 'applied');
    expect($draft->fresh()->lock_version)->toBe($draft->lock_version)
        ->and(AdminAuditLog::query()->where('action', 'lesson.package_imported')->count())->toBe(1)
        ->and(workFolders())->toBe([]);
});

it('previews and applies a zip without sharing a temporary folder', function () {
    $manifest = [
        'format' => 'cybercompass.content', 'format_version' => 1, 'kind' => 'lesson-sections', 'target' => ['lesson' => 'osi-model'],
        'operations' => [['op' => 'append', 'section' => ['heading' => 'Figure', 'type' => 'media', 'payload' => ['media' => ['file' => 'media/f.png', 'alt' => 'A figure'], 'caption' => null]]]],
    ];
    $transfer = upload(zipBytes(['manifest.json' => json_encode($manifest), 'media/f.png' => smallPng()]), 'figure.ccpkg.zip');
    expect($transfer['report']['valid'])->toBeTrue()
        // The preview's folder is gone, so the apply step has to unpack the stored file again.
        ->and(workFolders())->toBe([]);

    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$transfer['id']}/apply")->assertAccepted()->assertJsonPath('data.status', 'applied');
    $media = collect(Lesson::query()->where('slug', 'osi-model')->first()->draft->snapshot['sections'])->last()['payload']['media'];
    expect(Storage::disk(config('cybercompass.uploads_disk'))->exists("lesson-media/{$media}.webp"))->toBeTrue()
        ->and(workFolders())->toBe([]);
});

it('takes a package of tens of megabytes through the upload endpoints', function () {
    config(['cybercompass.content_packages.part_bytes' => 8 * 1024 * 1024]);
    $entries = ['manifest.json' => json_encode(sectionsPackage())];
    for ($i = 0; $i < 5; $i++) {
        $entries["media/filler-{$i}.png"] = random_bytes(5 * 1024 * 1024); // incompressible, never referenced
    }
    $bytes = zipBytes($entries);
    expect(strlen($bytes))->toBeGreaterThan(25 * 1024 * 1024);

    $transfer = upload($bytes, 'large.ccpkg.zip');

    expect($transfer['status'])->toBe('ready')
        ->and($transfer['report']['valid'])->toBeTrue()
        ->and(ContentTransfer::query()->find($transfer['id'])->parts)->toBe(4)
        ->and(Storage::disk('local')->size("imports/{$transfer['id']}/package"))->toBe(strlen($bytes))
        ->and(workFolders())->toBe([]);
});

it('keeps transfers to the admin who started them', function () {
    $transfer = upload(json_encode(sectionsPackage()));
    $other = User::factory()->admin()->create();
    $id = $transfer['id'];

    asTransferAdmin($other)->getJson("/api/v1/admin/content/transfers/{$id}")->assertNotFound();
    asTransferAdmin($other)->postJson("/api/v1/admin/content/transfers/{$id}/apply")->assertNotFound();
    asTransferAdmin($other)->postJson("/api/v1/admin/content/transfers/{$id}/validate")->assertNotFound();
    asTransferAdmin($other)->deleteJson("/api/v1/admin/content/transfers/{$id}")->assertNotFound();
    asTransferAdmin($other)->getJson("/api/v1/admin/content/transfers/{$id}/download")->assertNotFound();
    asTransferAdmin(User::factory()->create())->getJson("/api/v1/admin/content/transfers/{$id}")->assertForbidden();
    Auth::forgetGuards();
    $this->getJson("/api/v1/admin/content/transfers/{$id}")->assertUnauthorized();

    expect(Lesson::query()->where('slug', 'osi-model')->first()->draft)->toBeNull();
});

it('explains that an expired upload has to be sent again', function () {
    $transfer = upload(json_encode(sectionsPackage()));
    ContentTransfer::query()->whereKey($transfer['id'])->update(['expires_at' => now()->subMinute()]);

    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$transfer['id']}/apply")
        ->assertStatus(410)
        ->assertJsonPath('message', 'This file expired and was removed. Upload or export the package again.');

    // Pruned: the row and the file are gone.
    $this->artisan('content:prune-transfers')->assertSuccessful();
    expect(ContentTransfer::query()->find($transfer['id']))->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses to apply a package with errors, or one whose lesson changed since the preview', function () {
    $broken = upload(json_encode(['format' => 'cybercompass.content', 'format_version' => 1, 'kind' => 'lesson-sections', 'target' => ['lesson' => 'osi-model'], 'operations' => [['op' => 'remove', 'key' => 'nope']]]));
    expect($broken['report']['valid'])->toBeFalse()
        ->and($broken['report']['errors'])->toHaveKey('operations.0.key');
    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$broken['id']}/apply")->assertUnprocessable();

    $transfer = upload(json_encode(sectionsPackage()));
    // Someone else changes the same lesson in a way that keeps the package valid: only the draft version moved.
    $other = sectionsPackage('Someone else.');
    $other['operations'][0]['section'] = ['key' => 'someone-else', 'heading' => 'Someone else', 'type' => 'prose', 'payload' => ['text' => 'Someone else.']];
    app(ImportPackage::class)->handle($this->admin, json_decode(json_encode($other), true), null);

    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$transfer['id']}/apply")->assertAccepted()->assertJsonPath('data.status', 'failed');
    expect(ContentTransfer::query()->find($transfer['id'])->error)->toContain('changed since this preview')
        ->and(collect(Lesson::query()->where('slug', 'osi-model')->first()->draft->snapshot['sections'])->last()['heading'])->toBe('Someone else');

    // Validating again previews against the current draft, and then it applies.
    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$transfer['id']}/validate")->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.report.valid', true);
    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$transfer['id']}/apply")->assertAccepted()->assertJsonPath('data.status', 'applied');
});

it('checks every part it receives', function () {
    $start = asTransferAdmin()->postJson('/api/v1/admin/content/transfers', ['file_name' => 'p.ccpkg.json', 'size' => 5000])->assertCreated()->json('data');
    $url = "/api/v1/admin/content/transfers/{$start['id']}/parts";

    asTransferAdmin()->call('PUT', "{$url}/1", [], [], [], [], 'too short')->assertUnprocessable();
    asTransferAdmin()->call('PUT', "{$url}/3", [], [], [], [], 'x')->assertConflict();
    asTransferAdmin()->call('PUT', "{$url}/1", [], [], [], [], str_repeat('a', 4096))->assertOk();
    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$start['id']}/complete")->assertUnprocessable();

    expect(ContentTransfer::query()->find($start['id'])->status)->toBe('failed');
    asTransferAdmin()->postJson('/api/v1/admin/content/transfers', ['file_name' => 'p.exe', 'size' => 300 * 1024 * 1024])
        ->assertUnprocessable()->assertJsonValidationErrors(['file_name', 'size']);
});

it('ends a failed step in "failed" with a reason and no temporary files', function () {
    $transfer = upload(json_encode(sectionsPackage()));
    ContentTransfer::query()->whereKey($transfer['id'])->update(['status' => ContentTransfer::VALIDATING]);
    // The planner is final; any object with plan() stands in for it.
    app()->instance(PackagePlanner::class, new class
    {
        public function plan(): never
        {
            throw new RuntimeException('planner crashed');
        }
    });
    $job = new ValidateTransfer($transfer['id']);

    // A crash: the step's folder is removed on the way out, then the queue calls failed().
    expect(fn () => $job->handle())->toThrow(RuntimeException::class)
        ->and(is_dir($job->dir()))->toBeFalse();
    $job->failed(new RuntimeException('planner crashed'));
    expect(ContentTransfer::query()->find($transfer['id']))->status->toBe('failed')
        ->error->toContain('unexpected error')->not->toContain('planner crashed');

    // A timeout kills the step mid-way, so failed() itself removes what it left.
    File::ensureDirectoryExists($job->dir().'/unpacked');
    file_put_contents($job->dir().'/package', 'half');
    $job->failed(new TimeoutExceededException('timed out'));
    expect(ContentTransfer::query()->find($transfer['id'])->error)->toContain('15 minutes')
        ->and(workFolders())->toBe([]);
});

it('validates pasted JSON without storing anything', function () {
    asTransferAdmin()->postJson('/api/v1/admin/content/packages/check', ['json' => json_encode(sectionsPackage())])
        ->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.documents.0.slug', 'osi-model');
    asTransferAdmin()->postJson('/api/v1/admin/content/packages/check', ['json' => '{"format": '])
        ->assertOk()->assertJsonPath('data.valid', false);

    asTransferAdmin()->postJson('/api/v1/admin/content/packages/check', ['json' => str_repeat('x', 2 * 1024 * 1024 + 1)])
        ->assertUnprocessable()->assertJsonPath('errors.json.0', 'Pasted text can be at most 2 MB. Upload the package as a .ccpkg.json file instead.');

    expect(ContentTransfer::query()->count())->toBe(0)
        ->and(Lesson::query()->where('slug', 'osi-model')->first()->draft)->toBeNull();
});

it('downloads a lesson or some of its sections as a JSON package', function () {
    $lesson = Lesson::query()->where('slug', 'osi-model')->firstOrFail();

    $whole = asTransferAdmin()->get("/api/v1/admin/content/lessons/{$lesson->id}/package")->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="osi-model.ccpkg.json"');
    $some = asTransferAdmin()->get("/api/v1/admin/content/lessons/{$lesson->id}/package?sections=the-seven-layers")->assertOk();

    expect(json_decode($whole->getContent(), true)['kind'])->toBe('lesson')
        ->and(json_decode($some->getContent(), true)['operations'])->toHaveCount(1)
        ->and(AdminAuditLog::query()->where('action', 'lesson.package_exported')->count())->toBe(2);
    asTransferAdmin(User::factory()->create())->get("/api/v1/admin/content/lessons/{$lesson->id}/package")->assertForbidden();
});

it('exports a zip with its images that imports again', function () {
    $zip = zipBytes(['manifest.json' => json_encode([
        'format' => 'cybercompass.content', 'format_version' => 1, 'kind' => 'post',
        'document' => ['slug' => 'zip-post', 'title' => 'Zip post', 'summary' => 'S', 'tags' => [], 'cover' => ['file' => 'media/c.png', 'alt' => 'Cover'],
            'sections' => [['key' => 'img', 'type' => 'media', 'payload' => ['media' => ['file' => 'media/c.png', 'alt' => 'Cover'], 'caption' => null]]]],
    ]), 'media/c.png' => smallPng()]);
    $imported = upload($zip, 'post.ccpkg.zip');
    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$imported['id']}/apply")->assertAccepted();
    $post = Post::query()->where('slug', 'zip-post')->firstOrFail();

    $export = asTransferAdmin()->postJson('/api/v1/admin/content/transfers/exports', ['type' => 'post', 'id' => $post->id])
        ->assertAccepted()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.report.images', 1)->json('data');
    $url = asTransferAdmin()->getJson("/api/v1/admin/content/transfers/{$export['id']}/download")->assertOk()->json('data.url');
    $file = asTransferAdmin()->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertDownload('zip-post.ccpkg.zip');

    $path = tempnam(sys_get_temp_dir(), 'exp').'.zip';
    file_put_contents($path, $file->streamedContent());
    $package = app(PackageReader::class)->read($path, 'zip-post.ccpkg.zip');
    try {
        expect($package['envelope']['document']['cover'])->toMatchArray(['alt' => 'Cover'])
            ->and(app(PackagePlanner::class)->plan($package['envelope'], $package['root'])->valid())->toBeTrue();
    } finally {
        PackageReader::cleanup($package['root']);
        unlink($path);
    }
    expect(AdminAuditLog::query()->where('action', 'post.package_exported')->exists())->toBeTrue()
        ->and(workFolders())->toBe([]);
});

it('exports a whole course', function () {
    $export = asTransferAdmin()->postJson('/api/v1/admin/content/transfers/exports', ['type' => 'course', 'id' => Course::query()->where('slug', 'applied-cryptography')->value('id')])
        ->assertAccepted()->json('data');

    expect($export['status'])->toBe('ready')
        ->and($export['report']['kind'])->toBe('course')
        ->and($export['report']['documents'])->toBe(Lesson::query()->whereHas('chapter.course', fn ($q) => $q->where('slug', 'applied-cryptography'))->where('status', '!=', 'archived')->count());
});

it('uploads straight to S3 with presigned part URLs', function () {
    $mock = new MockHandler;
    $mock->append(new Result(['UploadId' => 'upload-1']));
    config(['filesystems.disks.transfers-test' => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'ap-southeast-1', 'bucket' => 'transfers-bucket', 'handler' => $mock, 'throw' => true],
        'cybercompass.content_packages.disk' => 'transfers-test', 'cybercompass.content_packages.part_bytes' => 5 * 1024 * 1024]);

    $start = asTransferAdmin()->postJson('/api/v1/admin/content/transfers', ['file_name' => 'big.ccpkg.zip', 'size' => 12 * 1024 * 1024])->assertCreated()->json('data');

    expect($start['upload']['mode'])->toBe('s3')
        ->and($start['upload']['parts'])->toHaveCount(3)
        ->and($start['upload']['parts'][2]['url'])->toContain('transfers-bucket')->toContain('partNumber=3')->toContain('uploadId=upload-1')->toContain('X-Amz-Signature');
    // Parts never pass through the API when S3 takes them.
    asTransferAdmin()->call('PUT', "/api/v1/admin/content/transfers/{$start['id']}/parts/1", [], [], [], [], 'x')->assertNotFound();

    $completed = null;
    $mock->append(function (CommandInterface $command) use (&$completed) {
        $completed = $command;

        return new Result([]);
    });
    // The size check, then the validation step: exists, download (not a zip, so the preview lists the error).
    $size = new Result(['ContentLength' => 12 * 1024 * 1024]);
    $mock->append($size, $size, new Result(['Body' => Utils::streamFor('not a zip')]));
    asTransferAdmin()->postJson("/api/v1/admin/content/transfers/{$start['id']}/complete", ['parts' => [
        ['number' => 2, 'etag' => '"b"'], ['number' => 1, 'etag' => '"a"'], ['number' => 3, 'etag' => '"c"'],
    ]])->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.report.valid', false);

    expect($completed->getName())->toBe('CompleteMultipartUpload')
        ->and($completed['UploadId'])->toBe('upload-1')
        ->and(array_column($completed['MultipartUpload']['Parts'], 'PartNumber'))->toBe([1, 2, 3])
        ->and(ContentTransfer::query()->find($start['id'])->upload_id)->toBeNull()
        ->and(workFolders())->toBe([]);
});

it('removes temporary folders a crash left behind', function () {
    $old = PackageReader::workDir().'/transfer-999-ValidateTransfer';
    $fresh = PackageReader::workDir().'/transfer-998-ApplyTransfer';
    File::ensureDirectoryExists($old);
    File::ensureDirectoryExists($fresh);
    touch($old, now()->subHours(2)->getTimestamp());

    $this->artisan('content:prune-transfers')->assertSuccessful();

    expect(is_dir($old))->toBeFalse()->and(is_dir($fresh))->toBeTrue();
    File::deleteDirectory(PackageReader::workDir());
});

it('deletes a transfer and its file', function () {
    $transfer = upload(json_encode(sectionsPackage()));

    asTransferAdmin()->deleteJson("/api/v1/admin/content/transfers/{$transfer['id']}")->assertNoContent();
    expect(ContentTransfer::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
