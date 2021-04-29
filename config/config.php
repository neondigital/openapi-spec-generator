<?php

/*
 * OpenAPI Generator configuration
 */
return [

    'info' => [
        'title' => 'Test API',
        'description' => 'My awesome API',
        'version' => '1.0.0',
    ],

    'operations' => [
        'v1.posts.store' => [
            'summary' => 'Create a new Post',
            'description' => 'Some longer text to explain what is going on',
            'tags' => ['Posts']
        ],
    ],

    'tag_groups' => [
        'CMS' => [
            'Posts',
        ],
    ]
];
