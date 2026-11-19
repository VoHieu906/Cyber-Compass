<?php

it('lists the seven career paths with their key domains', function () {
    $this->getJson('/api/v1/careers')
        ->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.0.slug', 'soc-analyst')
        ->assertJsonStructure(['data' => [['slug', 'title', 'summary', 'domains' => [['slug', 'name', 'required_level']], 'topic_count']]]);
});

it('shows a career with responsibilities, skills, suggested courses and a staged roadmap', function () {
    $response = $this->getJson('/api/v1/careers/penetration-tester')->assertOk();

    $response->assertJsonPath('data.domains.0.slug', 'web-security')
        ->assertJsonPath('data.domains.0.required_level', 3)
        ->assertJsonPath('data.suggested_courses.0.slug', 'web-security-foundations')
        ->assertJsonCount(3, 'data.roadmap.stages')
        ->assertJsonPath('data.roadmap.stages.2.topics.0.slug', 'injection-attacks')
        ->assertJsonPath('data.roadmap.stages.2.topics.0.requires', ['web-basics']);

    expect($response->json('data.responsibilities'))->toHaveCount(5);
});

it('orders every roadmap so prerequisites come in an earlier stage', function () {
    foreach (collect($this->getJson('/api/v1/careers')->json('data'))->pluck('slug') as $slug) {
        $stages = $this->getJson("/api/v1/careers/{$slug}")->json('data.roadmap.stages');
        $stageOf = collect($stages)->flatMap(fn ($s) => collect($s['topics'])->mapWithKeys(fn ($t) => [$t['slug'] => $s['stage']]));

        foreach ($stages as $stage) {
            foreach ($stage['topics'] as $topic) {
                foreach ($topic['requires'] as $required) {
                    expect($stageOf[$required] ?? null)->toBeLessThan($stage['stage'], "{$slug}: {$topic['slug']} needs {$required} earlier");
                }
            }
        }
    }
});

it('returns 404 for unknown careers', function () {
    $this->getJson('/api/v1/careers/astronaut')->assertNotFound();
});
