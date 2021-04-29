<?php

/*
 * OpenAPI Generator configuration
 */
return [

    'info' => [
        'title' => 'My JSON:API',
        'description' => 'JSON:API built using Laravel',
        'version' => '1.0.0',
    ],

    'operations' => [
        'v1_posts_index' => [
            'summary' => 'Create a new Post',
            'description' => 'Some longer text to explain what is going on',
            'extra_tags' => ['Blog']
        ],
    ],

    'tag_groups' => [
        [
            'name' => 'CMS Management',
            'tags' => [
                'Categories',
                'Comments',
                'Posts',
            ]
        ],
    ]
];
