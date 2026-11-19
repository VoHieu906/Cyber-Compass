<?php

namespace Tests;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests run against the real learning content (seeded once per run by
     * RefreshDatabase, then each test is rolled back), so they also prove the content is valid.
     */
    protected bool $seed = true;

    protected string $seeder = ContentSeeder::class;
}
