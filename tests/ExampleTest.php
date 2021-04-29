<?php

namespace LaravelJsonApi\OpenApiSpec\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders\DatabaseSeeder;
use OpenApiGenerator;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /** @test */
    public function true_is_true()
    {
        OpenApiGenerator::generate();

        $this->assertTrue(true);
    }
}
