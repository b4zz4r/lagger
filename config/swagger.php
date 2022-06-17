<?php

return [
  "outputPath" => "public/storage",
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
    'url' => 'https://petstore.swagger.io/v2'
  ],
  'tags' => [
    'name' => 'pet',
    'description' => 'Everything about your Pets',
    'externalDocs' => [
      'description' => 'find more',
      'url' => 'http://swagger.io'
    ],
    'name' => 'store',
    'description' => 'Access to Petstore orders',
    'name' => 'user',
    'description' => 'Operations about user',
    'externalDocs' => [
      'description' => 'Find out more about our store',
      'url' => 'http://swagger.io'
    ]
  ]  
];
