<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'amazon_product_data' => [
        'key' => env('RAPIDAPI_KEY'),
        'host' => env('RAPIDAPI_AMAZON_PRODUCT_HOST', 'amazon-product-data-api.p.rapidapi.com'),
        'base_url' => env('RAPIDAPI_AMAZON_PRODUCT_BASE_URL', 'https://amazon-product-data-api.p.rapidapi.com'),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'text_model' => env('OPENROUTER_TEXT_MODEL'),
        'image_model' => env('OPENROUTER_IMAGE_MODEL'),
        'app_name' => env('OPENROUTER_APP_NAME', 'A+ Content Maker'),
        'site_url' => env('OPENROUTER_SITE_URL'),
    ],

    'admin' => [
        'name' => env('ADMIN_NAME', 'A+ Content Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
