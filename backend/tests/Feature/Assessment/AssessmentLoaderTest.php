<?php

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Modules\Assessment\Services\AssessmentLoader;

function bankFile(): array
{
    return require database_path('seeders/content/assessment.php');
}

it('loads the content file: 5 questions per domain at difficulties 1, 1, 2, 2, 3, with one correct option each', function () {
    $questions = AssessmentQuestion::query()->with('domain', 'options')->get();

    expect($questions)->toHaveCount(40);
    foreach ($questions->groupBy('domain.slug') as $domain => $group) {
        expect($group->pluck('difficulty')->sort()->values()->all())->toBe([1, 1, 2, 2, 3], $domain);
    }
    foreach ($questions as $question) {
        expect($question->options->where('is_correct', true))->toHaveCount(1, $question->key);
    }
});

it('reloads idempotently, matching questions by key', function () {
    $before = AssessmentQuestion::query()->pluck('id', 'key');

    $report = app(AssessmentLoader::class)->load(bankFile());

    expect($report)->toMatchArray(['questions' => 40, 'archived' => []])
        ->and(AssessmentQuestion::query()->pluck('id', 'key')->all())->toBe($before->all());
});

it('archives a question removed from the file, and brings it back when it returns', function () {
    $file = bankFile();
    unset($file['questions']['net-https-port']);

    expect(app(AssessmentLoader::class)->load($file)['archived'])->toBe(['net-https-port'])
        ->and(AssessmentQuestion::query()->where('key', 'net-https-port')->value('is_archived'))->toBeTrue();

    $user = User::factory()->create();
    $this->actingAs($user, 'web')->postJson('/api/v1/assessments/initial-knowledge/attempts')->assertJsonPath('data.total', 39);

    app(AssessmentLoader::class)->load(bankFile());
    expect(AssessmentQuestion::query()->where('key', 'net-https-port')->value('is_archived'))->toBeFalse();
});

it('rejects an invalid file without writing anything, listing every problem', function () {
    $file = bankFile();
    $file['questions']['net-https-port']['answer'] = 9;
    $file['questions']['net-dns-purpose']['domain'] = 'astrology';
    $file['questions']['Bad Key'] = ['domain' => 'linux', 'difficulty' => 5, 'prompt' => '', 'explanation' => 'x', 'options' => ['only one'], 'answer' => 0];

    expect(fn () => app(AssessmentLoader::class)->load($file))->toThrow(
        InvalidArgumentException::class,
        'question net-https-port: answer must be the index of one of its options',
    );

    try {
        app(AssessmentLoader::class)->load($file);
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())
            ->toContain('question net-dns-purpose: unknown domain "astrology"')
            ->toContain('question Bad Key: the key must be lowercase')
            ->toContain('question Bad Key: difficulty must be 1, 2 or 3')
            ->toContain('question Bad Key: prompt is required')
            ->toContain('question Bad Key: options must be a list of 2 to 6 labels');
    }
    expect(AssessmentQuestion::query()->count())->toBe(40)
        ->and(AssessmentQuestion::query()->where('key', 'net-dns-purpose')->first()->domain->slug)->toBe('networking');
});

it('refuses to change the number of options of an answered question', function () {
    $question = AssessmentQuestion::query()->where('key', 'linux-pwd')->firstOrFail();
    $attempt = AssessmentAttempt::query()->create(['user_id' => User::factory()->create()->id, 'assessment_id' => $question->assessment_id, 'started_at' => now()]);
    AssessmentAnswer::query()->create(['assessment_attempt_id' => $attempt->id, 'assessment_question_id' => $question->id, 'position' => 1]);

    $file = bankFile();
    $file['questions']['linux-pwd']['options'][] = 'echo $PWD';

    expect(fn () => app(AssessmentLoader::class)->load($file))->toThrow(InvalidArgumentException::class, 'Give the changed question a new key');
});

it('offers one assessment at a time', function () {
    $file = bankFile();
    $file['assessment']['slug'] = 'second-edition';

    app(AssessmentLoader::class)->load($file);

    expect(Assessment::query()->active()->pluck('slug')->all())->toBe(['second-edition']);
});
