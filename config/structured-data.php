<?php

declare(strict_types=1);

return [
    'enabled' => true,

    'schema_path' => 'Schemas',

    'classes' => [
        'class' => class_exists('Statamic\\Statamic')
            ? 'Superinteractive\\StructuredData\\Contexts\\StatamicSchemaContext'
            : 'Superinteractive\\StructuredData\\Contexts\\LaravelSchemaContext',

        'factory' => class_exists('Statamic\\Statamic')
            ? 'Superinteractive\\StructuredData\\Factories\\StatamicSchemaContextFactory'
            : 'Superinteractive\\StructuredData\\Factories\\LaravelSchemaContextFactory',
    ],
];
