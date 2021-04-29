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
    public function generate($serverKey)
    {
        $server = config('jsonapi.servers.' . $serverKey);
        
        // Initial OpenAPI object
        $openapi = new \cebe\openapi\spec\OpenApi([
            'openapi' => '3.0.2',
            'info' => [
                'title' => config('openapi.servers.'.$serverKey.'.info.title'),
                'description' => config('openapi.servers.'.$serverKey.'.info.description'),
                'version' => config('openapi.servers.'.$serverKey.'.info.version'),
            ],
            'paths' => [],
            "components" => $this->getDefaultComponents(),
            'x-tagGroups' => config('openapi.servers.'.$serverKey.'.tag_groups'),
        ]);

        // Load JSON:API Server
        $jsonapiServer = new $server(app(), $serverKey);
        
        // Add server to OpenAPI spec
        $openapi->__set('servers', [new Server([
            'url' => "{serverURL}",
            "description" => "provide your server URL",
            "variables" => [
                "serverURL" => new ServerVariable([
                    "default" => $jsonapiServer->url(""),
                    "description" => "path for server",
                ])
            ]
        ])]);
        
        $allSchemas = [];
        $allRequests = [];
        $allParameters = [];
        
        // Get all Laravel routes associated with this JSON:API Server
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(function (\Illuminate\Routing\Route $route) use ($serverKey) {
                return StrStr::contains($route->getName(), $serverKey);
            });

        $routeMethods = [];

        foreach ($routes as $route) {
            $uri = $route->uri;
            $routeUri = str_replace($route->getPrefix(), '', $uri);
            
            $requiresPath = \Str::contains($routeUri, '{');
            
            if ($requiresPath) {
                $schemaName = \Str::between($routeUri, '{', '}');
            } else {
                $schemaName = str_replace('/', '', $routeUri);
            }
            
            $schemaName = (string)\Str::of($schemaName)->plural()->replace('_', '-');

            $sh = $jsonapiServer->schemas()->schemaFor($schemaName);
            $schema = new $sh($jsonapiServer);
            //$schema->withSchemas($server->schemas()); // method doesn't exist anymore. can't find out what it did

            foreach ($route->methods() as $method) {
                $parameters = [];
                $responses = new Responses([]);

                if ($method === 'HEAD') {
                    continue;
                }

                if ($method === 'GET') {
                    if (!$requiresPath) {
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

                if (!in_array($method, ['DELETE'])) {
                    $responses->addResponse(200, new Response([
                        'description' => "$method $schemaName",
                        "content" => [
                            "application/vnd.api+json" => new MediaType([
                                "schema" => new Schema([
                                    "oneOf" => [new Reference([
                                        '$ref' => "#/components/schemas/" . $schemaName
                                    ])]
                                ])
                            ])
                        ],
                    ]));
                } else {
                    $responses->addResponse(200, new Response([
                        'description' => "$method $schemaName",
                    ]));
                }

                if(in_array($method, ['POST'])) {
                    $responses->addResponse(201, new Response([
                        'description' => "$method $schemaName",
                        "content" => [
                            "application/vnd.api+json" => new MediaType([
                                "schema" => new Schema([
                                    "oneOf" => [new Reference([
                                        '$ref' => "#/components/schemas/" . $schemaName
                                    ])]
                                ])
                            ])
                        ],
                    ]));
                }

                if(in_array($method, ['POST','PATCH'])) {
                    $responses->addResponse(202, new Response([
                        'description' => "$method $schemaName",
                        "content" => [
                            "application/vnd.api+json" => new MediaType([
                                "schema" => new Schema([
                                    "oneOf" => [new Reference([
                                        '$ref' => "#/components/schemas/" . $schemaName
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
                
                if ($requiresPath) {
                    $models = ($schema::model())::all();
                    array_push($parameters, new Parameter([
                        'name' => $schemaName,
                        'in' => 'path',
                        'required' => true,
                        'allowEmptyValue' => false,
                        "examples" => optional($models)->mapWithKeys(function ($model) use($schema) {return [
                            $model->{$schema->id()->column() ?? $model->getRouteKeyName()} => new Example([
                                "value" => $model->{$schema->id()->column() ?? $model->getRouteKeyName()}
                            ])
                        ];})->toArray(),
                        'schema' => new Schema([
                            'title' => $schemaName,
                        ]),
                    ]));
                }

                // Make nice summaries
                $action = StrStr::of($route->getName())->explode('.')->last();

                switch ($action) {
                    case "index":
                        $summary = "Get all " . $schemaName;
                        break;

                    case "show":
                        $summary = "Get a " . $schemaName;
                        break;

                    case "store":
                        $summary = "Create a new " . $schemaName;
                        break;

                    case "update":
                        $summary = "Update the " . $schemaName;
                        break;

                    case "delete":
                        $summary = "Delete the " . $schemaName;
                        break;

                    default:
                        $summary = ucfirst($action);
                        break;
                }

                if (!isset($routeMethods[$routeUri])) {
                    $routeMethodsethods[$routeUri] = [];
                }

                $operationId = str_replace(".", "_", $route->getName());

                if (in_array($method, ['POST', 'PATCH'])) {
                    $requestBody = ['$ref' => "#/components/requestBodies/" . $schemaName . "_" . strtolower($method)];

                    $routeMethods[$routeUri][strtolower($method)] = new Operation([
                            "summary" => config('openapi.servers.'.$serverKey.'.operations.' . $operationId . ".summary") ?? $summary,
                            "description" => config('openapi.servers.'.$serverKey.'.operations.' . $operationId . ".description") ?? "",
                            "operationId" => $operationId,
                            "parameters" => $parameters,
                            "responses" => $responses,
                            "requestBody" => $requestBody,
                            "tags" => array_merge([ucfirst($schemaName)], config('openapi.servers.'.$serverKey.'.operations.' . $operationId . ".extra_tags", []))
                        ]);
                } else {
                    $routeMethods[$routeUri][strtolower($method)] = new Operation([
                            "summary" => config('openapi.servers.'.$serverKey.'.operations.' . $operationId . ".summary") ?? $summary,
                            "description" => config('openapi.servers.'.$serverKey.'.operations.' . $operationId . ".description") ?? "",
                            "operationId" => $operationId,
                            "parameters" => $parameters,
                            "responses" => $responses,
                            "tags" => array_merge([ucfirst($schemaName)], config('openapi.servers.'.$serverKey.'.operations.' . $operationId . ".extra_tags", []))
                    ]);
                }
            }
        }

        foreach ($jsonapiServer->schemas()->types() as $schemaName) {
            $schema = $jsonapiServer->schemas()->schemaFor($schemaName);
            $schemaNamePlural = (string)\Str::of($schemaName)->plural()->replace('_', '-');
            
            $methods = ['GET', 'PATCH', 'POST', 'DELETE'];
            
            foreach ($methods as $method) {
                $fieldSchemas = [];
                $includedSchemas = [];
                $parameters = [];
                
                if ($method === 'GET') {
                    foreach ($schema->fields() as $field) {
                        if ($field instanceof Relation) {
                            try {
                                $relationPlural = \Str::plural($field->name());
                                $includedSchemas = array_merge($includedSchemas, [
                                    new Reference([
                                        '$ref' => "#/components/schemas/" . $relationPlural . "_data"
                                    ])
                                ]);
                            } catch (\Throwable $exception) {
                                continue;
                            }
                            continue;
                        }
                    }
                    
                    $schemaData = $this->getSwaggerSchema($jsonapiServer, $schema, $schemaName, $schemaNamePlural, $method, $allSchemas);
                    
                    $allSchemas = array_merge($allSchemas, [$schemaName => new Schema([
                        'title' => $schemaName,
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
                                        '$ref' => "#/components/schemas/" . $schemaNamePlural . "_data"
                                    ])
                                ]
                            ]),
                            // "included" => new Schema([
                            //     "type" => Type::OBJECT,
                            //     "title" => "included",
                            //     "properties" => $includedSchemas
                            // ])
                        ],
                    ])]);
                    
                    $allSchemas = array_merge($allSchemas, [$schemaName . "_data" => new Schema([
                        'title' => $schemaName . "_data",
                        'properties' => $schemaData->__get('properties'),
                    ])]);
                }
                
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
                    $allRequests = array_merge($allRequests, [$schemaName . "_" . strtolower($method) => new RequestBody([
                        'description' => $schemaName . "_" . strtolower($method),
                        'content' => [
                            'application/vnd.api+json' => new MediaType([
                                "schema" => new Schema([
                                    "properties" => [
                                        "data" => new Schema([
                                            "title" => 'data',
                                            "type" => Type::OBJECT,
                                            "oneOf" => [
                                                new Reference([
                                                    '$ref' => "#/components/schemas/" . $schemaNamePlural . "_data"
                                                ])
                                            ]
                                        ])
                                    ]
                                ])
                            ])
                        ]
                    ])]);
                }
            }
        }

        // Add paths to OpenApi spec
        foreach ($routeMethods as $key => $method) {
            $openapi->paths["{$key}"] = new PathItem(array_merge([
                "description" => $schemaName,
            ], $method));
        }
        
        $openapi->components->__set('schemas', array_merge($this->getDefaultSchema(), $allSchemas));
        $openapi->components->__set('requestBodies', $allRequests);
        $openapi->components->__set('parameters', array_merge($openapi->components->parameters, $allParameters));

        if ($openapi->validate()) {
            $yaml = \cebe\openapi\Writer::writeToYaml($openapi);
        } else {
            dump($openapi->getErrors());
            throw new \Exception('Open API not valid.');
        }

        // Save to storage
        \Storage::put($serverKey . '_openapi.yaml', $yaml);

        return $yaml;
    }

    private function getSwaggerSchema($server, $schema, string $schemaName, string $schemaNamePlural, string $method,
                                      $allSchemas, $forIncludes = false) {
        if (isset($allSchemas[$schemaNamePlural])) return $all_schemas[$schemaNamePlural];
        $fieldSchemas = [];
        $relationSchemas = [];
        $model = $schema::model();
        $models = $model::all();
        $model = $model::first();
        foreach ($schema->fields() as $field) {
            if (in_array($method, ['DELETE'])) {
                continue;
            }
            $fieldSchema = new Schema([
                'title' => $field->name(),
                "type" => Type::OBJECT,
            ]);
            if ($field instanceof ID) continue;
            if (
                $field instanceof Str ||
                $field instanceof ID ||
                $field instanceof DateTime
            ) {
                $fieldSchema->__set('type', Type::STRING);
            }
            if ($field instanceof Boolean) {
                $fieldSchema->__set('type', Type::BOOLEAN);
            }
            if ($field instanceof Number) {
                $fieldSchema->__set('type', Type::NUMBER);
            }
            if (!($field instanceof Relation)) {
                try {
                    $fieldSchema->__set("example", optional($model)->{$field->column()});
                } catch (\Throwable $exception) {
                    // TODO: Figure out if the field is readonly
                }
            }
            if ($field instanceof Relation) {
                $relationSchema = new Schema([
                    'title' => $field->name(),
                ]);
                $relationLinkSchema = new Schema([
                    'title' => $field->name(),
                ]);
                $relationDataSchema = new Schema([
                    'title' => $field->name(),
                ]);
                $fieldName = \LaravelJsonApi\Core\Support\Str::dasherize(
                    \LaravelJsonApi\Core\Support\Str::plural($field->relationName())
                );
                $relationLinkSchema->__set('properties', [
                    'related' => new Schema([
                        'title' => 'related',
                        "type" => Type::STRING,
                    ]),
                    'self' => new Schema([
                        'title' => 'self',
                        "type" => Type::STRING,
                    ]),
                ]);
                $relationDataSchema->__set('properties', [
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
                $relationSchema->__set('properties', [
                    'links' => new Schema([
                        'title' => 'links',
                        'type' => Type::OBJECT,
                        "allOf" => [$relationLinkSchema],
                        "example" => $server->url([$fieldName,optional($model)->{$schema->id()->column() ?? optional($model)->getRouteKeyName()}]),
                    ]),
                    'data' => new Schema([
                        'title' => 'data',
                        "allOf" => [$relationDataSchema],
                    ]),
                ]);
                if ($field instanceof ToOne && in_array($fieldName, $server->schemas()->types())) {
                    $fieldSchema->__set('oneOf', [
                        $relationSchema
                    ]);
                }
                $relationSchemas = array_merge($relationSchemas, [$field->name() => $relationSchema]);
                continue;
            }
            $fieldSchemas = array_merge($fieldSchemas, [$field->name() => $fieldSchema]);
            unset($fieldSchema);
        }

        return new Schema([
            "type" => Type::OBJECT,
            "title" => "data",
            "properties" => [
                "type" => new Schema([
                    'title' => $schemaName,
                    'type' => Type::STRING,
                    'example' => $schemaNamePlural
                ]),
                "id" => new Schema([
                    'title' => 'id',
                    'type' => Type::STRING,
                    "example" => optional($model)->id
                ]),
                "attributes" => new Schema([
                    'title' => 'attributes',
                    'properties' => $fieldSchemas
                ]),
                // "relationships" => new Schema([
                //     'title' => 'relationships',
                //     'properties' => !empty($relationSchemas) ? $relationSchemas : []
                // ]),
                "links" => new Schema([
                    'title' => 'links',
                    "nullable" => true,
                    'properties' => [
                        "self" => new Schema([
                            "title" => "self",
                            'type' => Type::STRING,
                            //"example" => $server->url([$schemaNamePlural,optional($model)->{$schema->id()->column() ?? optional($model)->getRouteKeyName()}]),
                        ])
                    ]
                ]),
            ]
        ]);
    }
                                      
    private function getDefaultSchema()
    {
        return [
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
    }
    
    protected function getDefaultComponents()
    {
        return new Components([
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
        ]);
    }
}
