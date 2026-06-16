<?php

/**
 * Konfigurasi Lighthouse — GraphQL server untuk Service 3.
 * File ini dibuat manual (bukan `php artisan vendor:publish`) agar
 * bisa langsung digunakan tanpa perlu jaringan saat build container.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'route' => [
        'prefix' => 'graphql',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Location
    |--------------------------------------------------------------------------
    | Path ke file SDL utama. Lighthouse men-scan file ini + import-nya.
    */
    'schema' => [
        'register' => base_path('graphql/schema.graphql'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespace Mappings
    |--------------------------------------------------------------------------
    | Default namespace untuk resolver, mutation, dll.
    */
    'namespaces' => [
        'models' => 'App\\Models',
        'queries' => 'App\\GraphQL\\Queries',
        'mutations' => 'App\\GraphQL\\Mutations',
        'subscriptions' => 'App\\GraphQL\\Subscriptions',
        'types' => 'App\\GraphQL\\Types',
        'interfaces' => 'App\\GraphQL\\Interfaces',
        'unions' => 'App\\GraphQL\\Unions',
        'scalars' => 'App\\GraphQL\\Scalars',
        'directives' => 'App\\GraphQL\\Directives',
        'enums' => 'App\\Enums',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'max_query_complexity' => 100,
        'max_query_depth' => 10,
        'disable_introspection' => false,
    ],
];
