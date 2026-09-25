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


    'mercadopago' => [

        /*
        |--------------------------------------------------------------------------
        | API
        |--------------------------------------------------------------------------
        */

        'base_url' =>
            env(
                'MERCADOPAGO_BASE_URL',
                'https://api.mercadopago.com'
            ),

        /*
        * Credencial privada.
        *
        * Solamente backend.
        */
        'access_token' =>
            env(
                'MERCADOPAGO_ACCESS_TOKEN'
            ),


        /*
        |--------------------------------------------------------------------------
        | Empresa
        |--------------------------------------------------------------------------
        |
        | Se utilizará como compañía responsable
        | del viaje enviado a Mercado Pago.
        |
        */

        'company' =>
            env(
                'MERCADOPAGO_COMPANY',
                env(
                    'APP_NAME',
                    'Reservas Pasajes'
                )
            ),


        /*
        |--------------------------------------------------------------------------
        | URLs WEB
        |--------------------------------------------------------------------------
        */

        'web' => [

            'success_url' =>
                env(
                    'MERCADOPAGO_WEB_SUCCESS_URL'
                ),

            'failure_url' =>
                env(
                    'MERCADOPAGO_WEB_FAILURE_URL'
                ),

            'pending_url' =>
                env(
                    'MERCADOPAGO_WEB_PENDING_URL'
                ),
        ],


        /*
        |--------------------------------------------------------------------------
        | URLs MOBILE
        |--------------------------------------------------------------------------
        |
        | Funcionarán posteriormente como Deep Links.
        |
        */

        'mobile' => [

            'success_url' =>
                env(
                    'MERCADOPAGO_MOBILE_SUCCESS_URL'
                ),

            'failure_url' =>
                env(
                    'MERCADOPAGO_MOBILE_FAILURE_URL'
                ),

            'pending_url' =>
                env(
                    'MERCADOPAGO_MOBILE_PENDING_URL'
                ),
        ],



        'webhook_secret' =>
            env(
                'MERCADOPAGO_WEBHOOK_SECRET'
            ),
    ],

];
