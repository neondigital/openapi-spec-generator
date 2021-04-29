<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Support\JsonApi;

use LaravelJsonApi\Core\Server\Server as BaseServer;

class Server extends BaseServer
{
    /**
     * The base URI namespace for this server.
     * 
     * @return string
     */
    protected function baseUri(): string
    {
        return '/api/v1';
    }

    /**
     * Bootstrap the server when it is handling an HTTP request.
     *
     * @return void
     */
    public function serving(): void
    {
        // no-op
    }

    /**
     * Get the server's list of schemas.
     *
     * @return array
     */
    protected function allSchemas(): array
    {
        return [
            Posts\PostSchema::class,
        ];
    }
}
