<?php

namespace LaravelJsonApi\OpenApiSpec\Tests;

use LaravelJsonApi\Laravel\Http\Controllers\JsonApiController;
use LaravelJsonApi\OpenApiSpec\OpenApiServiceProvider;
use LaravelJsonApi\OpenApiSpec\Tests\Support\JsonApi\Server;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Support\Facades\App;

abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('jsonapi.servers', [
            'v1' => Server::class,
        ]);
    }

    protected function defineRoutes($router)
    {
        $router->group(['prefix' => 'api', 'middleware' => 'api'], function() {
            $jsonApiRoute = App::make(\LaravelJsonApi\Laravel\Routing\Registrar::class);

            $jsonApiRoute->server('v1')
                ->prefix('v1')
                ->resources(function ($server) {
                    $server->resource('posts', JsonApiController::class);
                });
        });
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/Support/Database/Migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            \LaravelJsonApi\Encoder\Neomerx\ServiceProvider::class,
            \LaravelJsonApi\Laravel\ServiceProvider::class,
            OpenApiServiceProvider::class
        ];
    }

    protected function getPackageAliases($app)
    {      
        return [
            "OpenApiGenerator" => \LaravelJsonApi\OpenApiSpec\Facades\GeneratorFacade::class,
            "JsonApi" => \LaravelJsonApi\Core\Facades\JsonApi::class,
            "JsonApiRoute" => \LaravelJsonApi\Laravel\Facades\JsonApiRoute::class
        ];
    }
}
