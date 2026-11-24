<?php

namespace App\Modules\Content\Packages;

use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\CourseGraph;
use App\Models\Lesson;
use App\Models\Post;
use App\Modules\Blog\Services\PostWorkingCopy;
use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Documents\BlockDocument;
use App\Modules\Content\Documents\WorkingCopy;
use App\Modules\Content\Services\ContentFileMedia;
use App\Modules\Content\Services\ContentImporter;
use App\Modules\Content\Services\LessonWorkingCopy;
use App\Modules\Learning\Services\ExerciseReferences;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Turns a content package (D67) into draft snapshots without writing anything.
 *
 * - The envelope names the format, its version and the kind of package.
 * - Sections come in the stored shape {key, type, heading, payload, sources} and are validated by
 *   the same working copy the editor saves through, so nothing here knows any block type.
 * - A section keeps its UUID when the package names it by key or uuid; a new one gets
 *   sectionUuid(slug, key). A package never invents a UUID.
 * - Removing a section an exercise question teaches is refused (D30); the snapshot validation
 *   keeps architecture references whole (D57).
 */
final class PackagePlanner
{
    public const FORMAT = 'cybercompass.content';

    public const VERSION = 1;

    public const KINDS = ['lesson', 'lesson-sections', 'post', 'post-sections', 'course'];

    public const OPERATIONS = ['append', 'insert_after', 'insert_before', 'replace', 'remove'];

    private const KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Package field => working copy field, for lessons; the reverse maps error keys back. */
    private const LESSON_FIELDS = ['slug' => 'slug', 'title' => 'title', 'minutes' => 'estimated_minutes', 'summary' => 'summary', 'objectives' => 'objectives', 'takeaways' => 'key_takeaways', 'resources' => 'resources'];

    private const POST_FIELDS = ['slug' => 'slug', 'title' => 'title', 'summary' => 'summary', 'tags' => 'tags', 'cover' => 'cover'];

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly LessonWorkingCopy $lessonCopy,
        private readonly PostWorkingCopy $postCopy,
        private readonly ExerciseReferences $references,
    ) {}

    public function plan(array $envelope, ?string $root): PackagePlan
    {
        $kind = $envelope['kind'] ?? null;
        $plan = new PackagePlan(is_string($kind) ? $kind : '?', is_string($envelope['generator'] ?? null) ? Str::limit($envelope['generator'], 200) : null, app(ContentFileMedia::class));

        if (! $this->checkEnvelope($envelope, $root, $plan)) {
            return $plan;
        }

        foreach ($this->jobs($envelope, $plan) as $job) {
            $job['type'] === 'lesson' ? $this->planLesson($job, $root, $plan) : $this->planPost($job, $root, $plan);
        }

        $slugs = array_map(fn ($d) => "{$d['type']}:{$d['slug']}", $plan->documents);
        foreach (array_diff_assoc($slugs, array_unique($slugs)) as $i => $_) {
            $plan->error("documents.{$i}", 'This package changes the same document twice.');
        }

        return $plan;
    }

    private function checkEnvelope(array $e, ?string $root, PackagePlan $plan): bool
    {
        if (($e['format'] ?? null) !== self::FORMAT) {
            $plan->error('format', 'This is not a CyberCompass content package: "format" must be "'.self::FORMAT.'".');
        }
        $version = $e['format_version'] ?? null;
        if (! is_int($version) || $version < 1) {
            $plan->error('format_version', 'Write "format_version": '.self::VERSION.'.');
        } elseif ($version > self::VERSION) {
            $plan->error('format_version', "This package uses format version {$version}, but this app reads version ".self::VERSION.'. Write it again for version '.self::VERSION.'.');
        }
        if (! in_array($e['kind'] ?? null, self::KINDS, true)) {
            $plan->error('kind', 'Unknown kind. Use one of: '.implode(', ', self::KINDS).'.');
        } elseif ($e['kind'] === 'course' && $root === null) {
            $plan->error('kind', 'A course package holds several lessons and must be a .ccpkg.zip bundle.');
        }
        foreach (['target', 'base'] as $field) {
            if (isset($e[$field]) && (! is_array($e[$field]) || ($e[$field] !== [] && array_is_list($e[$field])))) {
                $plan->error($field, "\"{$field}\" must be an object.");
            }
        }

        return $plan->valid();
    }

    /**
     * One job per document the package touches.
     *
     * @return list<array{type: string, at: string, slug: mixed, document: ?array, operations: ?array, course: mixed, chapter: mixed, fingerprint: mixed}>
     */
    private function jobs(array $e, PackagePlan $plan): array
    {
        $target = $e['target'] ?? [];
        $fingerprint = $e['base']['lesson_fingerprint'] ?? $e['base']['post_fingerprint'] ?? null;
        $job = fn (string $type, string $at, mixed $slug, ?array $document, ?array $operations, mixed $chapter = null) => [
            'type' => $type, 'at' => $at, 'slug' => $slug, 'document' => $document, 'operations' => $operations,
            'course' => $target['course'] ?? null, 'chapter' => $chapter ?? ($target['chapter'] ?? null), 'fingerprint' => $fingerprint,
        ];
        $object = function (mixed $value, string $at) use ($plan): ?array {
            if (is_array($value) && ! array_is_list($value)) {
                return $value;
            }
            $plan->error($at, 'Expected an object.');

            return null;
        };
        $operations = function () use ($e, $plan): ?array {
            $ops = $e['operations'] ?? null;
            $max = config('cybercompass.content_packages.max_operations');
            if (! is_array($ops) || ! array_is_list($ops) || $ops === [] || count($ops) > $max) {
                $plan->error('operations', "List between 1 and {$max} operations.");

                return null;
            }

            return $ops;
        };

        return match ($e['kind']) {
            'lesson' => ($doc = $object($e['document'] ?? null, 'document')) ? [$job('lesson', 'document', $doc['slug'] ?? null, $doc, null)] : [],
            'post' => ($doc = $object($e['document'] ?? null, 'document')) ? [$job('post', 'document', $doc['slug'] ?? null, $doc, null)] : [],
            'lesson-sections' => ($ops = $operations()) ? [$job('lesson', 'operations', $target['lesson'] ?? null, null, $ops)] : [],
            'post-sections' => ($ops = $operations()) ? [$job('post', 'operations', $target['post'] ?? null, null, $ops)] : [],
            'course' => $this->courseJobs($e, $plan, $job, $object),
        };
    }

    private function courseJobs(array $e, PackagePlan $plan, \Closure $job, \Closure $object): array
    {
        $documents = $e['documents'] ?? null;
        $max = config('cybercompass.content_packages.max_documents');
        if (! is_array($documents) || ! array_is_list($documents) || $documents === [] || count($documents) > $max) {
            $plan->error('documents', "List between 1 and {$max} lessons, each {\"chapter\", \"document\"}.");

            return [];
        }
        $jobs = [];
        foreach ($documents as $i => $item) {
            $item = $object($item, "documents.{$i}");
            $doc = $item ? $object($item['document'] ?? null, "documents.{$i}.document") : null;
            if ($doc) {
                $jobs[] = $job('lesson', "documents.{$i}.document", $doc['slug'] ?? null, $doc, null, $item['chapter'] ?? null);
            }
        }

        return $jobs;
    }

    private function planLesson(array $job, ?string $root, PackagePlan $plan): void
    {
        $at = $job['at'];
        $slugAt = $job['operations'] !== null ? 'target.lesson' : "{$at}.slug";
        if (! is_string($job['slug']) || $job['slug'] === '') {
            $plan->error($slugAt, 'Name the lesson by its slug.');

            return;
        }

        $lesson = Lesson::query()->with('chapter.course')->where('slug', $job['slug'])->first();
        $course = is_string($job['course']) ? Course::query()->where('slug', $job['course'])->first() : null;
        if ($job['course'] !== null && ! $course) {
            $plan->error('target.course', 'No course has this slug.');

            return;
        }

        if ($lesson) {
            if ($course && $lesson->chapter->course_id !== $course->id) {
                $plan->error($slugAt, "This slug belongs to a lesson of another course ({$lesson->chapter->course->slug}).");

                return;
            }
            $chapter = $lesson->chapter;
            $current = $this->lessonCopy->current($lesson);
            if ($job['chapter'] !== null && ($found = $this->chapter($chapter->course, $job['chapter'])) && $found->id !== $chapter->id) {
                $plan->warn($at, 'The lesson already exists in the chapter "'.$chapter->title.'"; a package does not move lessons, so the chapter is ignored.');
            }
        } elseif ($job['operations'] !== null) {
            $plan->error($slugAt, 'No lesson has this slug. To add a new lesson, send a "lesson" package.');

            return;
        } else {
            if (! $course) {
                $plan->error('target.course', 'A new lesson needs "target.course", the slug of its course.');

                return;
            }
            $chapter = $this->chapter($course, $job['chapter']);
            if (! $chapter) {
                $plan->error(str_starts_with($at, 'documents.') ? preg_replace('/\.document$/', '.chapter', $at) : 'target.chapter', 'A new lesson needs the title (or 1-based number) of an existing chapter of '.$course->slug.'.');

                return;
            }
            $lesson = new Lesson(['course_chapter_id' => $chapter->id, 'slug' => $job['slug']]);
            $lesson->setRelation('chapter', $chapter);
            $current = [
                'chapter_id' => $chapter->id, 'slug' => $job['slug'], 'title' => '', 'summary' => '', 'estimated_minutes' => 10,
                'objectives' => [], 'key_takeaways' => [], 'resources' => [], 'sections' => [], 'architecture' => null,
            ];
        }

        $input = WorkingCopy::toInput($current);
        foreach (self::LESSON_FIELDS as $from => $to) {
            if ($job['document'] !== null && array_key_exists($from, $job['document'])) {
                $input[$to] = $job['document'][$from];
            }
        }
        $input['chapter_id'] = $chapter->id;

        $this->planDocument('lesson', $lesson, $this->lessonCopy, $current, $input, $job, $root, $plan, self::LESSON_FIELDS + ['architecture' => 'architecture']);
    }

    private function planPost(array $job, ?string $root, PackagePlan $plan): void
    {
        $at = $job['at'];
        $slugAt = $job['operations'] !== null ? 'target.post' : "{$at}.slug";
        if (! is_string($job['slug']) || $job['slug'] === '') {
            $plan->error($slugAt, 'Name the post by its slug.');

            return;
        }
        $post = Post::query()->where('slug', $job['slug'])->first();
        if ($post) {
            $current = $this->postCopy->current($post);
        } elseif ($job['operations'] !== null) {
            $plan->error($slugAt, 'No post has this slug. To add a new post, send a "post" package.');

            return;
        } else {
            $post = new Post(['slug' => $job['slug']]);
            $current = ['slug' => $job['slug'], 'title' => '', 'summary' => '', 'cover' => null, 'tags' => [], 'sections' => []];
        }

        $input = WorkingCopy::toInput($current);
        foreach (self::POST_FIELDS as $from => $to) {
            if ($job['document'] !== null && array_key_exists($from, $job['document'])) {
                $input[$to] = $job['document'][$from];
            }
        }
        if (ContentFileMedia::isReference($input['cover'] ?? null)) {
            $errors = [];
            $input['cover'] = $root === null ? null : $plan->media->resolve($input['cover'], "{$at}.cover", $errors, $root);
            $root === null && $errors["{$at}.cover"] = ['Images need a .ccpkg.zip bundle with the file inside it.'];
            foreach ($errors as $path => $messages) {
                array_map(fn ($m) => $plan->error($path, $m), $messages);
            }
        }

        $this->planDocument('post', $post, $this->postCopy, $current, $input, $job, $root, $plan, self::POST_FIELDS);
    }

    /**
     * Applies the document's sections or the operations to the working copy, validates the result
     * like a draft save, and records the plan.
     *
     * @param  array<string, string>  $fields  package field => working copy field
     */
    private function planDocument(string $type, BlockDocument $doc, WorkingCopy $copy, array $current, array $input, array $job, ?string $root, PackagePlan $plan, array $fields): void
    {
        $at = $job['at'];
        $slug = $job['slug'];
        $isNew = ! $doc->exists;
        $currentSections = $current['sections'];
        $fingerprint = $isNew ? null : Fingerprint::of($currentSections);
        $stale = is_string($job['fingerprint']) && $fingerprint !== null && $job['fingerprint'] !== $fingerprint;
        if ($stale) {
            $plan->warn('base', 'This content was generated against an older version of "'.$slug.'": it changed since the overview was exported. Check the preview carefully.');
        }

        // The working list: one entry per section, with the package path it came from (null = untouched).
        $keys = ContentImporter::sectionKeys($currentSections);
        $entries = array_map(fn ($s, $k) => ['uuid' => $s['uuid'], 'key' => $k, 'section' => $s, 'origin' => null], $currentSections, $keys);
        $known = $isNew ? [] : array_flip($copy->knownUuids($doc));

        if ($job['document'] !== null) {
            $entries = $this->wholeDocument($job['document']['sections'] ?? null, $entries, $known, $at, $plan, $type, $slug);
        } else {
            foreach ($job['operations'] as $i => $op) {
                $entries = $this->operation($op, "operations.{$i}", $entries, $known, $stale, $plan, $type, $slug);
            }
        }
        // Operations or sections that failed above are left out; the rest is still validated, so
        // the author sees every problem in one round instead of one kind of error at a time.

        // Images named by file: resolved now (stored on apply), then treated as existing by validation.
        foreach ($entries as $i => $entry) {
            if ($entry['origin'] !== null) {
                $entries[$i]['section']['payload'] = $this->resolveImages($entry['section']['payload'] ?? null, "{$entry['origin']}.payload", $root, $plan);
            }
        }

        // Keys are not stored: the next overview derives them from headings again (D67).
        $derived = ContentImporter::sectionKeys(array_map(fn ($e) => ['type' => (string) ($e['section']['type'] ?? ''), 'heading' => is_string($e['section']['heading'] ?? null) ? trim($e['section']['heading']) : null], $entries));
        foreach ($entries as $i => $entry) {
            if ($entry['origin'] !== null && ! isset($entry['section']['key'])) {
                $entries[$i]['key'] = $derived[$i]; // the key the next overview will show for it
            }
            if ($entry['origin'] !== null && isset($entry['section']['key']) && $entry['section']['key'] !== $derived[$i]) {
                $plan->warn("{$entry['origin']}.key", "Keys come from headings, so the next overview lists this section as \"{$derived[$i]}\", not \"{$entry['section']['key']}\". Its uuid stays the same.");
            }
        }

        $byKey = array_column($entries, 'uuid', 'key');
        if ($type === 'lesson' && $job['document'] !== null && array_key_exists('architecture', $job['document'])) {
            $input['architecture'] = self::architectureByKey($job['document']['architecture'], $byKey);
        }
        $input['sections'] = array_map(fn ($e) => [
            'id' => $e['uuid'], 'type' => $e['section']['type'] ?? null, 'heading' => $e['section']['heading'] ?? null,
            'payload' => $e['section']['payload'] ?? null, 'sources' => $e['section']['sources'] ?? null,
        ], $entries);

        $kept = array_column($entries, 'uuid');
        if ($doc instanceof Lesson && $doc->exists) {
            $this->refuseRemovingReferenced($doc, $currentSections, $keys, $kept, $current['architecture'] ?? null, $input['architecture'] ?? null, $job, $plan);
        }

        $snapshot = null;
        try {
            $snapshot = $copy->assumingMedia($plan->media->pendingUuids())->validate($doc, $input);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $plan->error($this->packagePath($key, $entries, $at, $fields, $slug), $message);
                }
            }
        }

        $this->warnings($doc, $type, $isNew, $entries, $plan, $snapshot);

        $plan->documents[] = [
            'type' => $type,
            'slug' => $slug,
            'title' => (string) ($snapshot['title'] ?? $input['title'] ?? ''),
            'id' => $doc->getKey(),
            'is_new' => $isNew,
            'chapter' => $doc instanceof Lesson ? ['id' => $doc->chapter->id, 'title' => $doc->chapter->title] : null,
            'lock_version' => $isNew ? 0 : $copy->lockVersion($doc),
            'fingerprint' => $fingerprint,
            'snapshot' => $snapshot,
            'changes' => self::changes($currentSections, $snapshot['sections'] ?? [], $entries),
            'model' => $doc,
        ];
    }

    /** A whole document's sections: matched to existing ones by uuid or key, everything else replaced. */
    private function wholeDocument(mixed $sections, array $entries, array $known, string $at, PackagePlan $plan, string $type, string $slug): array
    {
        if (! is_array($sections) || ! array_is_list($sections)) {
            $plan->error("{$at}.sections", 'List the sections, in order.');

            return $entries;
        }
        $result = [];
        $used = [];
        foreach ($sections as $i => $section) {
            $path = "{$at}.sections.{$i}";
            if (! is_array($section) || array_is_list($section)) {
                $plan->error($path, 'Each section is an object {key, type, heading, payload, sources}.');

                continue;
            }
            $match = $this->locate($section, $path, $entries, false, $plan, required: false, known: $known);
            if ($match === false) {
                continue;
            }
            if ($match !== null && isset($used[$match])) {
                $plan->error($path, 'Another section of this package already matched the same existing section.');

                continue;
            }
            $key = $this->newKey($section, $path, $result, $plan, $match !== null ? $entries[$match]['key'] : null);
            if ($key === null) {
                continue;
            }
            $used[$match ?? -1 - $i] = true;
            $result[] = [
                'uuid' => $match !== null ? $entries[$match]['uuid'] : $this->freshUuid($type, $slug, $key, $entries, $result, $section['uuid'] ?? null, $known),
                'key' => $key,
                'section' => $section,
                'origin' => $path,
            ];
        }

        return $result;
    }

    private function operation(mixed $op, string $at, array $entries, array $known, bool $stale, PackagePlan $plan, string $type, string $slug): array
    {
        if (! is_array($op) || ! in_array($op['op'] ?? null, self::OPERATIONS, true)) {
            $plan->error("{$at}.op", 'Use one of: '.implode(', ', self::OPERATIONS).'.');

            return $entries;
        }
        $name = $op['op'];
        $index = null;
        if ($name !== 'append') {
            $index = $this->locate($op, $at, $entries, $stale, $plan, required: true);
            if ($index === null || $index === false) {
                return $entries;
            }
        }
        if ($name === 'remove') {
            array_splice($entries, $index, 1);

            return $entries;
        }

        $section = $op['section'] ?? null;
        if (! is_array($section) || array_is_list($section)) {
            $plan->error("{$at}.section", "\"{$name}\" needs a section {key, type, heading, payload, sources}.");

            return $entries;
        }
        $path = "{$at}.section";
        if ($name === 'replace') {
            $others = $entries;
            unset($others[$index]);
            $key = $this->newKey($section, $path, array_values($others), $plan, $entries[$index]['key']);
            if ($key !== null) {
                $entries[$index] = ['uuid' => $entries[$index]['uuid'], 'key' => $key, 'section' => $section, 'origin' => $path];
            }

            return $entries;
        }
        if (isset($section['uuid'])) {
            $plan->error("{$path}.uuid", 'A new section has no uuid; the app gives it one.');

            return $entries;
        }
        $key = $this->newKey($section, $path, $entries, $plan, null);
        if ($key === null) {
            return $entries;
        }
        $entry = ['uuid' => $this->freshUuid($type, $slug, $key, $entries, [], null, $known), 'key' => $key, 'section' => $section, 'origin' => $path];
        $position = match ($name) {
            'append' => count($entries),
            'insert_after' => $index + 1,
            'insert_before' => $index,
        };
        array_splice($entries, $position, 0, [$entry]);

        return $entries;
    }

    /**
     * The existing section an operation or a document section names by `key`, `uuid` or both.
     * Returns its index, null when none is named (and none is required), or false after an error.
     */
    private function locate(array $item, string $at, array $entries, bool $stale, PackagePlan $plan, bool $required, array $known = []): int|false|null
    {
        $key = $item['key'] ?? null;
        $uuid = $item['uuid'] ?? null;
        if ($uuid !== null && (! is_string($uuid) || ! Uuid::isValid($uuid))) {
            $plan->error("{$at}.uuid", 'This is not a section UUID.');

            return false;
        }
        $byUuid = $uuid === null ? null : array_search(strtolower($uuid), array_column($entries, 'uuid'), true);
        $byKey = is_string($key) ? array_search($key, array_column($entries, 'key'), true) : false;

        if ($uuid !== null && $byUuid === false) {
            if (! $required && isset($known[strtolower($uuid)])) {
                return null; // a section from an earlier version of this same document, brought back
            }
            $plan->error("{$at}.uuid", 'No section of this document has this UUID. Packages never invent UUIDs: leave it out for a new section.');

            return false;
        }
        if ($byUuid !== null && $byUuid !== false) {
            if (is_string($key) && $byKey !== false && $byKey !== $byUuid && $required) {
                $plan->error($at, 'The key and the uuid name two different sections. Use the pair from the same line of the overview.');

                return false;
            }

            return $byUuid;
        }
        if ($byKey !== false) {
            return $byKey;
        }
        if (! $required) {
            return null;
        }
        if (! is_string($key)) {
            $plan->error($at, 'Name the section by "key" or "uuid" (both are in the course overview).');
        } elseif ($stale) {
            $plan->error("{$at}.key", 'No section has the key "'.Str::limit($key, 60).'". The document changed since the overview was exported, so a heading may have been renamed: name the section by its uuid, or export a new overview.');
        } else {
            $plan->error("{$at}.key", 'No section has the key "'.Str::limit($key, 60).'". Use a key from the overview exactly; keys are not guessed from headings.');
        }

        return false;
    }

    /** A section's key: its own (checked), else the one it replaces, else made from its heading. Null after an error. */
    private function newKey(array $section, string $at, array $others, PackagePlan $plan, ?string $replacing): ?string
    {
        $taken = array_flip(array_column($others, 'key'));
        $key = $section['key'] ?? null;
        if ($key !== null) {
            if (! is_string($key) || strlen($key) > 80 || ! preg_match(self::KEY_PATTERN, $key)) {
                $plan->error("{$at}.key", 'Keys use lowercase letters, numbers and single hyphens (at most 80).');

                return null;
            }
            if (isset($taken[$key]) && $key !== $replacing) {
                $plan->error("{$at}.key", 'The key "'.$key.'" is already used in this document. Pick a new key, or use "replace" to change that section.');

                return null;
            }

            return $key;
        }
        if ($replacing !== null) {
            return $replacing;
        }
        $base = Str::slug((string) ($section['heading'] ?? '')) ?: (is_string($section['type'] ?? null) ? $section['type'] : 'section');
        $key = $base;
        for ($n = 2; isset($taken[$key]); $n++) {
            $key = "{$base}-{$n}";
        }

        return $key;
    }

    /** The UUID a new section gets: derived from slug and key, unless that one is already in use here. */
    private function freshUuid(string $type, string $slug, string $key, array $entries, array $more, mixed $named, array $known): string
    {
        if (is_string($named) && isset($known[strtolower($named)])) {
            return strtolower($named);
        }
        $uuid = $type === 'post' ? ContentImporter::postSectionUuid($slug, $key) : ContentImporter::sectionUuid($slug, $key);
        $used = array_flip([...array_column($entries, 'uuid'), ...array_column($more, 'uuid')]);

        return isset($used[$uuid]) ? (string) Str::uuid() : $uuid;
    }

    /** Replaces every {"file", "alt"} image reference in a payload with the media UUID it becomes. */
    private function resolveImages(mixed $node, string $at, ?string $root, PackagePlan $plan): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        if (ContentFileMedia::isReference($node)) {
            if ($root === null) {
                $plan->error($at, 'Images need a .ccpkg.zip bundle with the file inside it.');

                return '';
            }
            $errors = [];
            $uuid = $plan->media->resolve($node, $at, $errors, $root);
            foreach ($errors as $path => $messages) {
                array_map(fn ($m) => $plan->error($path, $m), $messages);
            }

            return $uuid ?? '';
        }
        foreach ($node as $k => $v) {
            $node[$k] = $this->resolveImages($v, "{$at}.{$k}", $root, $plan);
        }

        return $node;
    }

    /** Architecture nodes may name sections by key, as in content files. */
    private static function architectureByKey(mixed $architecture, array $byKey): mixed
    {
        if (! is_array($architecture) || ! is_array($architecture['nodes'] ?? null)) {
            return $architecture;
        }
        foreach ($architecture['nodes'] as $i => $node) {
            $ref = is_array($node) ? ($node['section'] ?? null) : null;
            if (is_string($ref) && ! Uuid::isValid($ref) && isset($byKey[$ref])) {
                $architecture['nodes'][$i]['section'] = $byKey[$ref];
            }
        }

        return $architecture;
    }

    /** D30 and D57: sections that exercise questions or architecture nodes point at stay. */
    private function refuseRemovingReferenced(Lesson $lesson, array $current, array $keys, array $kept, ?array $oldArchitecture, mixed $newArchitecture, array $job, PackagePlan $plan): void
    {
        $removed = array_diff(array_column($current, 'uuid'), $kept);
        if ($removed === []) {
            return;
        }
        $at = $job['operations'] !== null ? 'operations' : "{$job['at']}.sections";
        $questions = $this->references->forLesson($lesson->id);
        $architecture = is_array($newArchitecture) ? $newArchitecture : $oldArchitecture;
        $nodes = [];
        foreach ($architecture['nodes'] ?? [] as $node) {
            if (is_string($node['section'] ?? null)) {
                $nodes[$node['section']][] = $node['label'] ?? '?';
            }
        }
        foreach ($current as $i => $section) {
            if (! in_array($section['uuid'], $removed, true)) {
                continue;
            }
            $name = '"'.($section['heading'] ?? $keys[$i]).'" (key '.$keys[$i].')';
            if (isset($questions[$section['uuid']])) {
                $plan->error($at, "The section {$name} teaches exercise questions (".implode(', ', $questions[$section['uuid']]).') and cannot be removed. Keep it, or change the exercise first.');
            }
            if (isset($nodes[$section['uuid']])) {
                $plan->error($at, "The section {$name} is part of the lesson's architecture on the course map (".implode(', ', $nodes[$section['uuid']]).') and cannot be removed while it is.');
            }
        }
    }

    /** A working-copy error key as a place in the package. */
    private function packagePath(string $key, array $entries, string $at, array $fields, string $slug): string
    {
        if (preg_match('/^sections\.(\d+)(.*)$/', $key, $m)) {
            $entry = $entries[(int) $m[1]] ?? null;

            return ($entry['origin'] ?? "{$slug}.existing.".($entry['key'] ?? $m[1])).$m[2];
        }
        $field = explode('.', $key)[0];
        $from = array_search($field, $fields, true);
        $prefix = $at === 'operations' ? "{$slug}" : $at;
        if ($field === 'chapter_id') {
            return 'target.chapter';
        }

        return $prefix.'.'.($from !== false ? $from.substr($key, strlen($field)) : $key);
    }

    private function warnings(BlockDocument $doc, string $type, bool $isNew, array $entries, PackagePlan $plan, ?array $snapshot): void
    {
        foreach ($entries as $i => $entry) {
            if ($entry['origin'] === null) {
                continue;
            }
            $section = $snapshot['sections'][$i] ?? null;
            $blockType = $entry['section']['type'] ?? null;
            if ($section !== null && is_string($blockType) && $this->blocks->statesFacts($blockType, $section['payload']) && empty($section['sources'])) {
                $note = $this->blocks->factualNote($blockType, $section['payload']);
                $plan->warn($entry['origin'], 'This '.$blockType.' block states facts but lists no sources.'.($note ? " {$note}" : ''));
            }
            foreach ($section['sources'] ?? [] as $j => $source) {
                if (str_starts_with($source['url'], 'http://')) {
                    $plan->warn("{$entry['origin']}.sources.{$j}.url", 'This link is plain http; use https if the site supports it.');
                }
            }
        }

        $label = $type === 'post' ? 'post' : 'lesson';
        if ($isNew) {
            if ($doc instanceof Lesson) {
                $plan->warn($doc->slug, 'A new lesson joins no roadmap topic (D62): learners reach it from the course page only, until a topic in topics.php lists it.');
                if (CourseGraph::query()->where('course_id', $doc->chapter->course_id)->exists()) {
                    $plan->warn($doc->slug, 'The course has a course map; place the new lesson on it and connect it after publishing.');
                }
            }

            return;
        }
        if ($doc->draft !== null) {
            $plan->warn($doc->slug, "The {$label} has unpublished changes; the package is applied on top of that draft.");
        } elseif ($doc->updated_by === null && $doc->created_by === null) {
            $plan->warn($doc->slug, "This {$label} comes from a content file. After the import the database owns it, and content:import skips it until you run content:export to write it back (D19).");
        }
    }

    /** @return list<array{change: string, key: string, uuid: string, type: ?string, heading: ?string}> */
    private static function changes(array $current, array $final, array $entries): array
    {
        $before = array_column($current, null, 'uuid');
        $changes = [];
        foreach ($entries as $i => $entry) {
            $after = $final[$i] ?? $entry['section'];
            $old = $before[$entry['uuid']] ?? null;
            $same = $old !== null && Fingerprint::of([$old + ['uuid' => $entry['uuid']]]) === Fingerprint::of([['uuid' => $entry['uuid'], ...$after]]);
            $changes[] = [
                'change' => $old === null ? 'added' : ($same ? 'kept' : 'replaced'),
                'key' => $entry['key'], 'uuid' => $entry['uuid'],
                'type' => is_string($after['type'] ?? null) ? $after['type'] : null,
                'heading' => is_string($after['heading'] ?? null) ? $after['heading'] : null,
            ];
            unset($before[$entry['uuid']]);
        }
        $keys = ContentImporter::sectionKeys($current);
        foreach ($current as $i => $s) {
            if (isset($before[$s['uuid']])) {
                $changes[] = ['change' => 'removed', 'key' => $keys[$i], 'uuid' => $s['uuid'], 'type' => $s['type'], 'heading' => $s['heading']];
            }
        }

        return $changes;
    }

    private function chapter(Course $course, mixed $chapter): ?CourseChapter
    {
        return match (true) {
            is_int($chapter) => $course->chapters()->where('position', $chapter)->first(),
            is_string($chapter) => $course->chapters()->where('title', $chapter)->first(),
            default => null,
        };
    }
}
