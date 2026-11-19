<?php

it('reports ok when the database is reachable', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['data' => [
            'status' => 'ok',
            'database' => 'ok',
            'version' => config('app.version'),
        ]]);
});

it('is public', function () {
    $this->getJson('/api/v1/health')->assertOk();
});
