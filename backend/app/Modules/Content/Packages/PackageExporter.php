<?php

namespace App\Modules\Content\Packages;

use App\Models\Lesson;
use App\Models\Post;
use App\Modules\Blog\Services\PostWorkingCopy;
use App\Modules\Content\Services\ContentImporter;
use App\Modules\Content\Services\LessonWorkingCopy;
use Illuminate\Validation\ValidationException;

/**
 * Writes a lesson or post as a package (D67) from its working copy, the version an import would
 * change: the whole document, or only some sections as `replace` operations named by key and
 * uuid. Images stay media UUIDs, which import back on the same installation.
 */
final class PackageExporter
{
    public function __construct(
        private readonly LessonWorkingCopy $lessonCopy,
        private readonly PostWorkingCopy $postCopy,
    ) {}

    /** @param  ?list<string>  $keys  only these sections, as replace operations */
    public function lesson(Lesson $lesson, ?array $keys = null): array
    {
        $copy = $this->lessonCopy->current($lesson->loadMissing('chapter.course'));
        $sections = self::sections($copy['sections']);
        $base = ['lesson_fingerprint' => Fingerprint::of($copy['sections'])];
        $target = ['course' => $lesson->chapter->course->slug, 'chapter' => $lesson->chapter->title];

        if ($keys !== null) {
            return self::envelope('lesson-sections', ['lesson' => $lesson->slug] + $target, $base, ['operations' => self::replaceOperations($sections, $keys)]);
        }

        $architecture = $copy['architecture'] ?? null;
        if ($architecture !== null) {
            $keyOf = array_column($sections, 'key', 'uuid');
            foreach ($architecture['nodes'] as &$node) {
                if (isset($node['section'], $keyOf[$node['section']])) {
                    $node['section'] = $keyOf[$node['section']];
                }
            }
            unset($node);
        }

        return self::envelope('lesson', $target, $base, ['document' => [
            'slug' => $copy['slug'],
            'title' => $copy['title'],
            'minutes' => $copy['estimated_minutes'],
            'summary' => $copy['summary'],
            'objectives' => $copy['objectives'],
            'takeaways' => $copy['key_takeaways'],
            'resources' => $copy['resources'],
            'sections' => self::withoutUuids($sections),
            ...($architecture !== null ? ['architecture' => $architecture] : []),
        ]]);
    }

    /** @param  ?list<string>  $keys */
    public function post(Post $post, ?array $keys = null): array
    {
        $copy = $this->postCopy->current($post);
        $sections = self::sections($copy['sections']);
        $base = ['post_fingerprint' => Fingerprint::of($copy['sections'])];

        if ($keys !== null) {
            return self::envelope('post-sections', ['post' => $post->slug], $base, ['operations' => self::replaceOperations($sections, $keys)]);
        }

        return self::envelope('post', [], $base, ['document' => [
            'slug' => $copy['slug'],
            'title' => $copy['title'],
            'summary' => $copy['summary'],
            'tags' => $copy['tags'],
            'cover' => $copy['cover'],
            'sections' => self::withoutUuids($sections),
        ]]);
    }

    public static function json(array $envelope): string
    {
        return json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private static function envelope(string $kind, array $target, array $base, array $body): array
    {
        return [
            'format' => PackagePlanner::FORMAT,
            'format_version' => PackagePlanner::VERSION,
            'kind' => $kind,
            'generator' => 'CyberCompass export, '.now()->toDateString(),
            'target' => (object) $target,
            'base' => $base,
            ...$body,
        ];
    }

    /** Snapshot sections in the package shape, each with the key an operation would name it by. */
    private static function sections(array $sections): array
    {
        $keys = ContentImporter::sectionKeys($sections);

        return array_map(fn (array $s, string $key) => [
            'key' => $key,
            'uuid' => $s['uuid'],
            'type' => $s['type'],
            'heading' => $s['heading'],
            'payload' => $s['payload'],
            ...(! empty($s['sources']) ? ['sources' => $s['sources']] : []),
        ], $sections, $keys);
    }

    private static function withoutUuids(array $sections): array
    {
        return array_map(fn (array $s) => array_diff_key($s, ['uuid' => true]), $sections);
    }

    private static function replaceOperations(array $sections, array $keys): array
    {
        $byKey = array_column($sections, null, 'key');
        $missing = array_diff($keys, array_keys($byKey));
        if ($missing !== []) {
            throw ValidationException::withMessages(['sections' => ['No section has the key: '.implode(', ', $missing).'.']]);
        }

        return array_map(fn (string $key) => [
            'op' => 'replace',
            'key' => $key,
            'uuid' => $byKey[$key]['uuid'],
            'section' => array_diff_key($byKey[$key], ['uuid' => true]),
        ], array_values($keys));
    }
}
