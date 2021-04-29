<?php

namespace LaravelJsonApi\OpenApiSpec;

use cebe\openapi\spec\Components;
use cebe\openapi\spec\Example;
use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Server;
use cebe\openapi\spec\ServerVariable;
use cebe\openapi\spec\Type;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str as StrStr;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\Eloquent\Fields\Relations\ToOne;
use LaravelJsonApi\Eloquent\Fields\Str;

class OpenApiGenerator
{
    public function generate() {
        $servers = config('jsonapi.servers');

        // DEFAULT SCHEMA
        $default_schema = [
            'unauthorized' => new Schema([
                'title' => "unauthorized_error",
                "type" => Type::OBJECT,
                "properties" => [
                    "errors" => new Schema([
                        'title' => "errors",
                        "type" => Type::OBJECT,
                        "properties" => [
                            "detail" => new Schema([
                                'title' => "detail",
                                "type" => Type::STRING,
                                "example" => 'Unauthenticated.'
                            ]),
                            "status" => new Schema([
                                'title' => "status",
                                "type" => Type::STRING,
                                "example" => '401'
                            ]),
                            "title" => new Schema([
                                'title' => "title",
                                "type" => Type::STRING,
                                "example" => 'Unauthorized'
                            ]),
                        ]
                    ])
                ]
            ]),
            'forbidden' => new Schema([
                'title' => "unauthorized_error",
                "type" => Type::OBJECT,
                "properties" => [
                    "errors" => new Schema([
                        'title' => "errors",
                        "type" => Type::OBJECT,
                        "properties" => [
                            "detail" => new Schema([
                                'title' => "detail",
                                "type" => Type::STRING,
                                "example" => 'Forbidden.'
                            ]),
                            "status" => new Schema([
                                'title' => "status",
                                "type" => Type::STRING,
                                "example" => '403'
                            ]),
                            "title" => new Schema([
                                'title' => "title",
                                "type" => Type::STRING,
                                "example" => 'Forbidden'
                            ]),
                        ]
                    ])
                ]
            ]),
            'not_found' => new Schema([
                'title' => "404 Not Found",
                "type" => Type::OBJECT,
                "properties" => [
                    "errors" => new Schema([
                        'title' => "errors",
                        "type" => Type::OBJECT,
                        "properties" => [
                            "status" => new Schema([
                                'title' => "status",
                                "type" => Type::STRING,
                                "example" => '404'
                            ]),
                            "title" => new Schema([
                                'title' => "title",
                                "type" => Type::STRING,
                                "example" => 'Not Found'
                            ]),
                        ]
                    ])
                ]
            ]),
        ];
        // END DEFAULT SCHEMA

        // FOREACH OVER ALL SERVERS
        foreach ($servers as $key => $server) {
            $openapi = new \cebe\openapi\spec\OpenApi([
                'openapi' => '3.0.2',
                'info' => [
                    'title' => config('openapi.info.title'),
                    'description' => config('openapi.info.description'),
                    'version' => config('openapi.info.version'),
                ],
                'paths' => [],
                "components" => new Components([
                    'parameters' => [
                        'sort' => new Parameter([
                            "name" => "sort",
                            "in" => "query",
                            "description" => '[fields to sort by](https://jsonapi.org/format/#fetching-sorting)',
                            "required" => false,
                            "allowEmptyValue" => true,
                            "style" => "form",
                            "schema" => ["type" => "string"]
                        ]),
                        'pageSize' => new Parameter([
                            "name" => "page[size]",
                            "in" => "query",
                            "description" => 'size of page for paginated results',
                            "required" => false,
                            "allowEmptyValue" => true,
                            "schema" => ["type" => "integer"]
                        ]),
                        'pageNumber' => new Parameter([
                            "name" => "page[number]",
                            "in" => "query",
                            "description" => 'size of page for paginated results',
                            "required" => false,
                            "allowEmptyValue" => true,
                            "schema" => ["type" => "integer"]
                        ]),
                        'pageLimit' => new Parameter([
                            "name" => "page[limit]",
                            "in" => "query",
                            "description" => 'size of page for paginated results',
                            "required" => false,
                            "allowEmptyValue" => true,
                            "schema" => ["type" => "integer"]
                        ]),
                        'pageOffset' => new Parameter([
                            "name" => "page[offset]",
                            "in" => "query",
                            "description" => 'size of page for paginated results',
                            "required" => false,
                            "allowEmptyValue" => true,
                            "schema" => ["type" => "integer"]
                        ]),
                    ]
                ]),
                'x-tagGroups' => config('openapi.tag_groups'),
            ]);

            /** @var \LaravelJsonApi\Core\Server\Server $server */
            $server = new $server(app(), $key);
            $openapi->__set('servers', [new Server([
                'url' => "{serverURL}",
                "description" => "provide your server URL",
                "variables" => [
                    "serverURL" => new ServerVariable([
                        "default" => $server->url(""),
                        "description" => "path for server",
                    ])
                ]
            ])]);
            $all_schemas = [];
            $all_requests = [];
            $all_parameters = [];
            $routes = collect(Route::getRoutes()->getRoutes())->filter(function (\Illuminate\Routing\Route $route) use (
                $servers, $key
            ) {
                return \Str::contains($route->getName(), $key);
            });

            //print_r($key . "\n");

            $route_methods = [];

            // FOREACH OVER ROUTES
            /** @var \Illuminate\Routing\Route $route */
            foreach ($routes as $route) {


                // print_r($route->getController());
                // echo "\n";
                // echo "\n-----------\n";
                // print_r($route->getName());
                // echo "\n";
                // print_r($route->getPrefix());
                // echo "\n";
                // print_r($route->uri());
                // echo "\n";
                // print_r($route->getActionName());
                // echo "\n";
                //print_r($route->methods()[0]);
                
                // print_r($route->parameterNames());
                // echo "\n";

                $uri = $route->uri;
                /** @var string $route_uri route uri without api/serverkey prefix */
                $route_uri = str_replace("api/$key", '', $uri);
                // $route_uri now looks like /users for example.

                // $uri now e.g. api/v1/users/{user}/owns-machines
                $uri = \Str::replaceFirst($route->getPrefix(), '', $uri);
                // $uri now e.g. /owns-machines

                /** @var array $methods List of HTTP methods this route responds to */
                $methods = $route->methods();

                $requires_path = \Str::contains($uri, '{');
                $schema_name = null; // default in case we can't find the schema?

                // What's the purpose? In my test data, I don't have a case where {} occur. It takes /users
                // and the output of between() is still /users.
                $schema_name = \Str::between($uri, '{', '}');

                // TODO throw away contains condition and just replace? looks useless
                // remove slashes from schema name
                if (\Str::contains($schema_name, '/')) {
                    $schema_name = str_replace('/', '', $uri);
                }

                $schema_name_plural = (string)\Str::of($schema_name)->plural()->replace('_', '-');

                // from my perspective, this should always be true because between() returns the subject whether there is
                // parentheses or not.
                if ($schema_name) {
                    $sh = $server->schemas()->schemaFor($schema_name_plural);
                    /** @var \LaravelJsonApi\Eloquent\Schema $schema */
                    $schema = new $sh($server);
                    //$schema->withSchemas($server->schemas()); // method doesn't exist anymore. can't find out what it did
                }

                // FOREACH OVER ROUTE'S METHODS
                /** @var string $method */
                foreach ($methods as $method) {
                    $parameters = [];
                    $responses = new Responses([]);

                    if ($method === 'HEAD') {
                        continue;
                    }

                    if ($method === 'GET') {
                        if (!$requires_path) {
                            foreach ($schema->filters() as $filter) {
                                array_push($parameters, new Parameter([
                                    'name' => "filter[{$filter->key()}]",
                                    'in' => 'query',
                                    'required' => false,
                                    'allowEmptyValue' => true,
                                    'examples' => $schema::model()::all()->pluck($filter->key())->mapWithKeys(function ($f) {
                                        return [$f => new \cebe\openapi\spec\Example([
                                            'value' => $f,
                                        ])];
                                    })->toArray(),
                                    'schema' => new Schema([
                                        "type" => Type::STRING
                                    ]),
                                ]));
                            }
                            foreach ([
                                         "sort",
                                         "pageSize",
                                         "pageNumber",
                                         "pageLimit",
                                         "pageOffset",
                                     ] as $parameter) {
                                array_push($parameters, ['$ref' => "#/components/parameters/" . $parameter]);
                            }
                        }
                    }

                    // Why in_array? isn't $method always a string?
                    if (!in_array($method, ['DELETE'])) {
                        $responses->addResponse(200, new Response([
                            'description' => "$method $schema_name",
                            "content" => [
                                "application/vnd.api+json" => new MediaType([
                                    "schema" => new Schema([
                                        "oneOf" => [new Reference([
                                            '$ref' => "#/components/schemas/" . $schema_name_plural
                                        ])]
                                    ])
                                ])
                            ],
                        ]));
                    } else {
                        $responses->addResponse(200, new Response([
                            'description' => "$method $schema_name",
                        ]));
                    }

                    if(in_array($method, ['POST'])) {
                        $responses->addResponse(201, new Response([
                            'description' => "$method $schema_name",
                            "content" => [
                                "application/vnd.api+json" => new MediaType([
                                    "schema" => new Schema([
                                        "oneOf" => [new Reference([
                                            '$ref' => "#/components/schemas/" . $schema_name_plural
                                        ])]
                                    ])
                                ])
                            ],
                        ]));
                    }

                    if(in_array($method, ['POST','PATCH'])) {
                        $responses->addResponse(202, new Response([
                            'description' => "$method $schema_name",
                            "content" => [
                                "application/vnd.api+json" => new MediaType([
                                    "schema" => new Schema([
                                        "oneOf" => [new Reference([
                                            '$ref' => "#/components/schemas/" . $schema_name_plural
                                        ])]
                                    ])
                                ])
                            ],
                        ]));
                    }
                    $responses->addResponse(401, new Response([
                        'description' => "Unauthorized Action",
                        "content" => [
                            "application/vnd.api+json" => new MediaType([
                                "schema" => new Schema([
                                    "oneOf" => [new Reference([
                                        '$ref' => "#/components/schemas/unauthorized"
                                    ])]
                                ])
                            ])
                        ],
                    ]));
                    $responses->addResponse(403, new Response([
                        'description' => "Forbidden Action",
                        "content" => [
                            "application/vnd.api+json" => new MediaType([
                                "schema" => new Schema([
                                    "oneOf" => [new Reference([
                                        '$ref' => "#/components/schemas/forbidden"
                                    ])]
                                ])
                            ])
                        ],
                    ]));
                    $responses->addResponse(404, new Response([
                        'description' => "Content Not Found",
                        "content" => [
                            "application/vnd.api+json" => new MediaType([
                                "schema" => new Schema([
                                    "oneOf" => [new Reference([
                                        '$ref' => "#/components/schemas/not_found"
                                    ])]
                                ])
                            ])
                        ],
                    ]));
                    if ($requires_path) {
                        $models = ($schema::model())::all();
                        array_push($parameters, new Parameter([
                            'name' => $schema_name,
                            'in' => 'path',
                            'required' => true,
                            'allowEmptyValue' => false,
                            "examples" => optional($models)->mapWithKeys(function ($model) use($schema) {return [
                                $model->{$schema->id()->column() ?? $model->getRouteKeyName()} => new Example([
                                    "value" => $model->{$schema->id()->column() ?? $model->getRouteKeyName()}
                                ])
                            ];})->toArray(),
                            'schema' => new Schema([
                                'title' => $schema_name,
                            ]),
                        ]));
                    }

                    // Make nice summaries
                    $action = StrStr::of($route->getName())->explode('.')->last();

                    switch ($action) {
                        case "index":
                            $summary = "Get all " . $schema_name_plural;
                            break;

                        case "show":
                                $summary = "Get a " . $schema_name;
                                break;

                        case "store":
                            $summary = "Create a new " . $schema_name;
                            break;
                
                        case "update":
                            $summary = "Update the " . $schema_name . " resource";
                            break;

                        case "delete":
                            $summary = "Delete the " . $schema_name . " resource";
                            break;

                        default: 
                            $summary = ucfirst($action);
                            break;
                    }

                    if (!isset($route_methods[$route_uri])) {
                        $route_methods[$route_uri] = [];
                    }

                    $operationId = str_replace(".", "_", $route->getName());

                    if (in_array($method, ['POST', 'PATCH'])) {
                        //echo $method . " - " . $route_uri . "\n";
                        $requestBody = ['$ref' => "#/components/requestBodies/" . $schema_name_plural . "_" . strtolower($method)];
                        
                        $route_methods[$route_uri][strtolower($method)] = new Operation([
                                "summary" => config('openapi.operations.' . $operationId . ".summary") ?? $summary,
                                "description" => config('openapi.operations.' . $operationId . ".description") ?? "",
                                "operationId" => $operationId,
                                "parameters" => $parameters,
                                "responses" => $responses,
                                "requestBody" => $requestBody,
                                "tags" => array_merge([ucfirst($schema_name_plural)], config('openapi.operations.' . $operationId . ".extra_tags", []))
                            ]);

                    } else {

                        //echo $method . " - " . $route_uri . "\n";
                        $route_methods[$route_uri][strtolower($method)] = new Operation([
                                "summary" => config('openapi.operations.' . $operationId . ".summary") ?? $summary,
                                "description" => config('openapi.operations.' . $operationId . ".description") ?? "",
                                "operationId" => $operationId,
                                "parameters" => $parameters,
                                "responses" => $responses,
                                "tags" => array_merge([ucfirst($schema_name_plural)], config('openapi.operations.' . $operationId . ".extra_tags", []))
                        ]);
                    }
                    unset($parameters, $responses, $field_schemas);
                }
                // END FOREACH OVER ROUTE'S METHODS
            }
            // END FOREACH OVER ROUTES

            //print_r(array_keys($route_methods["api/v2/channels"]));



            // FOREACH OVER SCHEMAS
            foreach ($server->schemas()->types() as $schema_name) {
                /** @var \LaravelJsonApi\Eloquent\Schema $schema */
                $schema = $server->schemas()->schemaFor($schema_name);
                $schema_name_plural = (string)\Str::of($schema_name)->plural()->replace('_', '-');
                $methods = ['GET', 'PATCH', 'POST', 'DELETE'];
                foreach ($methods as $method) {
                    $field_schemas = [];
                    $included_schemas = [];
                    $parameters = [];
                    if ($method === 'GET') {
                        foreach ($schema->fields() as $field) {
                            if ($field instanceof Relation) {
                                try {
                                    $relation_plural = \Str::plural($field->name());
                                    $included_schemas = array_merge($included_schemas, [
                                        new Reference([
                                            '$ref' => "#/components/schemas/" . $relation_plural . "_data"
                                        ])
                                    ]);
                                } catch (\Throwable $exception) {
                                    continue;
                                }
                                continue;
                            }
                        }
                        $schema_data = $this->getSwaggerSchema($server, $schema, $schema_name, $schema_name_plural, $method, $all_schemas);
                        $all_schemas = array_merge($all_schemas, [$schema_name => new Schema([
                            'title' => $schema_name,
                            'properties' => [
                                "jsonapi" => new Schema([
                                    'title' => 'jsonapi',
                                    'properties' => [
                                        "version" => new Schema([
                                            "title" => "version",
                                            'type' => Type::STRING,
                                            "example" => "1.0"
                                        ])
                                    ]
                                ]),
                                "data" => new Schema([
                                    "oneOf" => [
                                        new Reference([
                                            '$ref' => "#/components/schemas/" . $schema_name_plural . "_data"
                                        ])
                                    ]
                                ]),
                                // "included" => new Schema([
                                //     "type" => Type::OBJECT,
                                //     "title" => "included",
                                //     "properties" => $included_schemas
                                // ])
                            ],
                        ])]);
                        $all_schemas = array_merge($all_schemas, [$schema_name . "_data" => new Schema([
                            'title' => $schema_name . "_data",
                            'properties' => $schema_data->__get('properties'),
                        ])]);
                    }
                    unset($included_schemas);
                    if (!empty($schema->fields()) && $method !== 'GET') {
                        $contents = [];
                        foreach ($schema->fields() as $field) {
                            $contents = array_merge($contents, [
                                $field->name() => new Schema([
                                    'title' => $field->name(),
                                    'type' => 'string',
                                ])
                            ]);
                        }
                        $all_requests = array_merge($all_requests, [$schema_name . "_" . strtolower($method) => new RequestBody([
                            'description' => $schema_name . "_" . strtolower($method),
                            'content' => [
                                'application/vnd.api+json' => new MediaType([
                                    "schema" => new Schema([
                                        "properties" => [
                                            "data" => new Schema([
                                                "title" => 'data',
                                                "type" => Type::OBJECT,
                                                "oneOf" => [
                                                    new Reference([
                                                        '$ref' => "#/components/schemas/" . $schema_name_plural . "_data"
                                                    ])
                                                ]
                                            ])
                                        ]
                                    ])
                                ])
                            ]
                        ])]);
                    }
                    unset($field_schemas, $parameters);
                }
            }
            // END FOREACH OVER SCHEMAS

            // Add paths to OpenApi spec
            foreach ($route_methods as $key => $method) {
                $key = "/" . $key;

                $openapi->paths["{$key}"] = new PathItem(array_merge([
                    "description" => $schema_name,
                ], $method));
            }
            $openapi->components->__set('schemas', array_merge($default_schema, $all_schemas));
            $openapi->components->__set('requestBodies', $all_requests);
            $openapi->components->__set('parameters', array_merge($openapi->components->parameters, $all_parameters));
        }
        // END FOREACH OVER ALL SERVERS

        if ($openapi->validate()) {
            $yaml = \cebe\openapi\Writer::writeToYaml($openapi);
        } else {
            dump($openapi->getErrors());
            throw new \Exception('Open API not valid.');
        }

        // Save to storage
        \Storage::put('openapi.yaml', $yaml);
    }

    private function getSwaggerSchema($server, $schema, string $schema_name, string $schema_name_plural, string $method,
                                      $all_schemas, $for_includes = false) {
        if (isset($all_schemas[$schema_name_plural])) return $all_schemas[$schema_name_plural];
        $field_schemas = [];
        $relation_schemas = [];
        $model = $schema::model();
        $models = $model::all();
        $model = $model::first();
        foreach ($schema->fields() as $field) {
            if (in_array($method, ['DELETE'])) {
                continue;
            }
            $field_schema = new Schema([
                'title' => $field->name(),
                "type" => Type::OBJECT,
            ]);
            if ($field instanceof ID) continue;
            if (
                $field instanceof Str ||
                $field instanceof ID ||
                $field instanceof DateTime
            ) {
                $field_schema->__set('type', Type::STRING);
            }
            if ($field instanceof Boolean) {
                $field_schema->__set('type', Type::BOOLEAN);
            }
            if ($field instanceof Number) {
                $field_schema->__set('type', Type::NUMBER);
            }
            if (!($field instanceof Relation)) {
                try {
                    $field_schema->__set("example", optional($model)->{$field->column()});
                } catch (\Throwable $exception) {
                    // TODO: Figure out if the field is readonly
                }
            }
            if ($field instanceof Relation) {
                $relation_schema = new Schema([
                    'title' => $field->name(),
                ]);
                $relation_link_schema = new Schema([
                    'title' => $field->name(),
                ]);
                $relation_data_schema = new Schema([
                    'title' => $field->name(),
                ]);
                $field_name = \LaravelJsonApi\Core\Support\Str::dasherize(
                    \LaravelJsonApi\Core\Support\Str::plural($field->relationName())
                );
                $relation_link_schema->__set('properties', [
                    'related' => new Schema([
                        'title' => 'related',
                        "type" => Type::STRING,
                    ]),
                    'self' => new Schema([
                        'title' => 'self',
                        "type" => Type::STRING,
                    ]),
                ]);
                $relation_data_schema->__set('properties', [
                    'type' => new Schema([
                        'title' => 'type',
                        "type" => Type::STRING,
                        "example" => $field_name,
                    ]),
                    'id' => new Schema([
                        'title' => 'id',
                        "type" => Type::STRING,
                        "example" => optional($model)->{$schema->id()->column() ?? optional($model)->getRouteKeyName()},
                    ]),
                ]);
                $relation_schema->__set('properties', [
                    'links' => new Schema([
                        'title' => 'links',
                        'type' => Type::OBJECT,
                        "allOf" => [$relation_link_schema],
                        "example" => $server->url([$field_name,optional($model)->{$schema->id()->column() ?? optional($model)->getRouteKeyName()}]),
                    ]),
                    'data' => new Schema([
                        'title' => 'data',
                        "allOf" => [$relation_data_schema],
                    ]),
                ]);
                if ($field instanceof ToOne && in_array($field_name, $server->schemas()->types())) {
                    $field_schema->__set('oneOf', [
                        $relation_schema
                    ]);
                }
                $relation_schemas = array_merge($relation_schemas, [$field->name() => $relation_schema]);
                continue;
            }
            $field_schemas = array_merge($field_schemas, [$field->name() => $field_schema]);
            unset($field_schema);
        }

        return new Schema([
            "type" => Type::OBJECT,
            "title" => "data",
            "properties" => [
                "type" => new Schema([
                    'title' => $schema_name,
                    'type' => Type::STRING,
                    'example' => $schema_name_plural
                ]),
                "id" => new Schema([
                    'title' => 'id',
                    'type' => Type::STRING,
                    "example" => optional($model)->id
                ]),
                "attributes" => new Schema([
                    'title' => 'attributes',
                    'properties' => $field_schemas
                ]),
                // "relationships" => new Schema([
                //     'title' => 'relationships',
                //     'properties' => !empty($relation_schemas) ? $relation_schemas : []
                // ]),
                "links" => new Schema([
                    'title' => 'links',
                    "nullable" => true,
                    'properties' => [
                        "self" => new Schema([
                            "title" => "self",
                            'type' => Type::STRING,
                            //"example" => $server->url([$schema_name_plural,optional($model)->{$schema->id()->column() ?? optional($model)->getRouteKeyName()}]),
                        ])
                    ]
                ]),
            ]
        ]);
    }
}
