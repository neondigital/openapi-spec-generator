<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders\DatabaseSeeder;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;
use OpenApiGenerator;
use Symfony\Component\Yaml\Yaml;

class GenerateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_spec_is_yaml()
    {
        $openapiYaml = OpenApiGenerator::generate();

        $spec = Yaml::parse($openapiYaml);

        $this->assertEquals('My JSON:API', $spec['info']['title']);
    }

    public function test_spec_file_generated()
    {
        OpenApiGenerator::generate();

        $openapiYaml = \Storage::get('openapi.yaml');

        $spec = Yaml::parse($openapiYaml);

        $this->assertEquals('My JSON:API', $spec['info']['title']);
    }
}
