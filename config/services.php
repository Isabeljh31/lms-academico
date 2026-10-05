<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Fotos de perfil, portadas de curso, materiales y entregas.
    // Sin CLOUDINARY_URL, App\Services\Multimedia\SubidorArchivos cae
    // automáticamente al disco local "public" (ver ARQUITECTURA.md).
    'cloudinary' => [
        'url' => env('CLOUDINARY_URL'),
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    ],

    // Bot de sugerencias (módulos 6 y 7). Qué proveedor usa
    // App\Services\Bot\Contracts\ProveedorIA (registrado en
    // AppServiceProvider): "groq" (gratuito, por defecto durante
    // desarrollo/pruebas del curso) o "claude" (de pago). Sin la key del
    // proveedor activo, el bot corre en modo simulado.
    'ia' => [
        'proveedor' => env('IA_PROVEEDOR', 'groq'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
    ],

    // ERS RF-100 a RF-106: API de integración de matrículas.
    'integracion' => [
        'limite_por_minuto' => (int) env('API_INTEGRACION_LIMITE_POR_MINUTO', 60),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

];
