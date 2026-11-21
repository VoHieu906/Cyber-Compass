<?php

use App\Enums\LessonStatus;
use App\Models\Assessment;
use App\Models\AssessmentOption;
use App\Models\Domain;
use App\Models\Lesson;
use App\Models\Topic;
use App\Models\User;
use App\Modules\Assessment\Actions\SaveAnswer;
use App\Modules\Assessment\Actions\StartAttempt;
use App\Modules\Assessment\Actions\SubmitAttempt;
use App\Modules\Learning\Actions\RecordLessonProgress;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/** A seeded topic that has a prerequisite, and that prerequisite. */
function mapTopicWithPrerequisite(): array
{
    $topic = Topic::query()->whereHas('prerequisites')->with('prerequisites')->orderBy('id')->firstOrFail();

    return [$topic, $topic->prerequisites->first()];
}

function mapCompleteTopic(User $user, Topic $topic): void
{
    foreach ($topic->lessons()->visibleToLearners()->get() as $lesson) {
        passLesson($user, $lesson, 300);
    }
}

function mapRow(array $data, string $slug): array
{
    return collect($data['topics'])->firstWhere('slug', $slug);
}

it('requires a session', function () {
    $this->getJson('/api/v1/knowledge-map')->assertUnauthorized();
});

it('maps every studyable topic by domain without a goal or assessment', function () {
    $data = $this->actingAs($this->user, 'web')->getJson('/api/v1/knowledge-map')->assertOk()->json('data');

    $expected = Topic::query()->whereHas('lessons', fn ($q) => $q->visibleToLearners())->pluck('slug')->sort()->values()->all();
    expect(collect($data['topics'])->pluck('slug')->sort()->values()->all())->toBe($expected)
        ->and(collect($data['domains'])->pluck('slug')->all())->toBe(Domain::query()->orderBy('position')->pluck('slug')->all())
        ->and(collect($data['domains'])->pluck('score')->unique()->all())->toBe([null]);

    [$topic, $prerequisite] = mapTopicWithPrerequisite();
    expect(mapRow($data, $topic->slug))->toMatchArray(['status' => 'locked', 'in_path' => false])
        ->and(mapRow($data, $topic->slug)['requires'])->toContain($prerequisite->slug)
        ->and($data['edges'])->toContain(['from' => $prerequisite->slug, 'to' => $topic->slug]);
    foreach ($data['topics'] as $row) {
        expect($row['status'])->toBe($row['requires'] === [] ? 'available' : 'locked');
    }
});

it('opens a topic once its prerequisites are completed and counts domain completion', function () {
    [$topic, $prerequisite] = mapTopicWithPrerequisite();
    mapCompleteTopic($this->user, $prerequisite);

    $data = $this->actingAs($this->user, 'web')->getJson('/api/v1/knowledge-map')->assertOk()->json('data');

    expect(mapRow($data, $prerequisite->slug))->toMatchArray(['status' => 'completed', 'percent' => 100]);
    if (count(mapRow($data, $topic->slug)['requires']) === 1) {
        expect(mapRow($data, $topic->slug)['status'])->toBe('available');
    }
    $domain = collect($data['domains'])->firstWhere('slug', $prerequisite->domain->slug);
    expect($domain['completion']['completed'])->toBeGreaterThan(0)
        ->and($domain['completion']['percent'])->toBe((int) round($domain['completion']['completed'] / $domain['completion']['total'] * 100));
});

it('shows the latest assessment score per domain', function () {
    $attempt = app(StartAttempt::class)($this->user, Assessment::query()->active()->firstOrFail())['attempt'];
    foreach ($attempt->answers()->with('question.domain')->get() as $answer) {
        $right = $answer->question->domain->slug === 'networking';
        $option = AssessmentOption::query()->where('assessment_question_id', $answer->assessment_question_id)->where('is_correct', $right)->first();
        app(SaveAnswer::class)($attempt, $answer->assessment_question_id, $option->id);
    }
    app(SubmitAttempt::class)($attempt);

    $domains = collect($this->actingAs($this->user, 'web')->getJson('/api/v1/knowledge-map')->json('data.domains'))->keyBy('slug');

    expect($domains['networking'])->toMatchArray(['score' => 100, 'band' => 'strength'])
        ->and($domains['linux'])->toMatchArray(['score' => 0, 'band' => 'gap']);
});

it('marks the topics on my active roadmap', function () {
    $this->actingAs($this->user, 'web')->putJson('/api/v1/me/learning-goal', ['career' => 'soc-analyst'])->assertOk();
    $path = $this->postJson('/api/v1/learning-path/generate')->assertCreated()->json('data');

    $data = $this->getJson('/api/v1/knowledge-map')->json('data');
    $onPath = collect($data['topics'])->where('in_path', true)->pluck('slug')->sort()->values()->all();

    expect($onPath)->toBe(collect($path['nodes'])->pluck('topic.slug')->sort()->values()->all());
});

it('leaves out topics whose lessons are all hidden from learners', function () {
    $topic = Topic::query()->whereHas('lessons')->orderBy('id')->firstOrFail();
    Lesson::query()->whereIn('id', $topic->lessons()->pluck('lessons.id'))->update(['status' => LessonStatus::Archived]);

    $data = $this->actingAs($this->user, 'web')->getJson('/api/v1/knowledge-map')->json('data');

    expect(collect($data['topics'])->pluck('slug'))->not->toContain($topic->slug)
        ->and(collect($data['edges'])->pluck('from'))->not->toContain($topic->slug);
});

it('returns lessons with summary and status for the drawer', function () {
    [, $prerequisite] = mapTopicWithPrerequisite();
    $lesson = $prerequisite->lessons()->visibleToLearners()->orderBy('topic_lessons.position')->firstOrFail();
    app(RecordLessonProgress::class)->start($this->user, $lesson);

    $row = mapRow($this->actingAs($this->user, 'web')->getJson('/api/v1/knowledge-map')->json('data'), $prerequisite->slug);

    expect($row['status'])->toBe('in_progress')
        ->and(collect($row['lessons'])->firstWhere('slug', $lesson->slug))->toMatchArray(['summary' => $lesson->summary, 'status' => 'started']);
});
