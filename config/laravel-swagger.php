<?php

return [
    'openapi' => '3.0.3',
    'outputPath' => public_path('swagger.json'),
    'info' => [
        'title' => 'Sample Pet Store App',
        'description' => 'This is a sample server for a pet store.',
        'termsOfService' => 'http://example.com/terms/',
        'contact' => [
            'name' => 'API Support',
            'url' => 'http://www.example.com/support',
            'email' => 'support@example.com',
        ],
        'license' => [
            'name' => 'Apache 2.0',
            'url' => 'https://www.apache.org/licenses/LICENSE-2.0.html',
        ],
        'version' => '1.0.1',
    ],
    'externalDocs' => [
        'description' => 'Find out more about Swagger',
        'url' => 'http://swagger.io'
    ],
    'servers' => [
        [
            'url' => 'https://petstore.swagger.io/v2',
        ]
    ],
    'tags' => [
        [
            'name' => 'Posts',
            'description' => 'Everything about your Post',
            'externalDocs' => [
                'description' => 'find more',
                'url' => 'http://swagger.io'
            ],
        ]
    ]
];
