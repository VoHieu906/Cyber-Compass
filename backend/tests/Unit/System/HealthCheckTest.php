<?php

use App\Modules\System\Services\HealthCheck;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;

it('reports degraded, not an error, when the database is unreachable', function () {
    Log::spy();
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('select')->andThrow(new RuntimeException('connection refused'));

    $result = (new HealthCheck($connection))->run();

    expect($result)->toMatchArray(['status' => 'degraded', 'database' => 'down']);
});

it('reports ok when the database answers', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('select')->once()->with('select 1')->andReturn([]);

    expect((new HealthCheck($connection))->run())
        ->toMatchArray(['status' => 'ok', 'database' => 'ok']);
});
