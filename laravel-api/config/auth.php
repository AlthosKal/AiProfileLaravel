<?php

use Modules\Auth\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'sanctum'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 15,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    'login' => [

        /*
        |--------------------------------------------------------------------------
        | IP-Based Rate Limiting (MiddlewaresFramework Layer)
        |--------------------------------------------------------------------------
        |
        | Límite por dirección IP para prevenir ataques de fuerza bruta desde
        | la misma ubicación. Se ejecuta ANTES del controller.
        |
        | - max_attempts: Número máximo de intentos permitidos
        | - decay_minutes: Tiempo en minutos para resetear el contador
        |
        | Response: HTTP 429 (Too Many Requests)
        | Bypasseable: Sí (cambiando IP/VPN)
        |
        */
        'ip' => [
            'max_attempts' => (int) env('LOGIN_RATE_LIMIT_IP_MAX_ATTEMPTS', 3),
            'decay_minutes' => (int) env('LOGIN_RATE_LIMIT_IP_DECAY_MINUTES', 1),
        ],

        /*
        |--------------------------------------------------------------------------
        | Email-Based Rate Limiting (MiddlewaresFramework Layer)
        |--------------------------------------------------------------------------
        |
        | Límite por email para prevenir ataques distribuidos sobre una cuenta
        | específica. Se ejecuta ANTES del controller.
        |
        | - max_attempts: Número máximo de intentos permitidos
        | - decay_minutes: Tiempo en minutos para resetear el contador
        |
        | Response: HTTP 429 (Too Many Requests)
        | Bypasseable: No (limita por email independiente de la IP)
        |
        */
        'email' => [
            'max_attempts' => (int) env('LOGIN_RATE_LIMIT_EMAIL_MAX_ATTEMPTS', 10),
            'decay_minutes' => (int) env('LOGIN_RATE_LIMIT_EMAIL_DECAY_MINUTES', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Net Rate Limiting (MiddlewaresFramework Layer)
    |--------------------------------------------------------------------------
    |
    | Protección adicional contra ataques DDoS MUY agresivos con límites
    | extremadamente altos que NO interfieren con el flujo normal del sistema.
    |
    | Este es un "safety net" (red de seguridad) como última línea de defensa
    | a nivel de software y de la app, contra ataques de más de 100+ req/seg desde la misma IP.
    |
    | - max_attempts: Número muy alto de intentos (30+ por minuto)
    | - decay_minutes: Tiempo en minutos para resetear el contador
    |
    | Response: HTTP 422 (Validation Error) con mensaje personalizado
    | Bypasseable: Sí (cambiando IP)
    |
    */
    'safety_net' => [
        'max_attempts' => (int) env('SAFETY_NET_MAX_ATTEMPTS', 30),
        'decay_minutes' => (int) env('SAFETY_NET_DECAY_MINUTES', 1),
    ],
    /*
    |--------------------------------------------------------------------------
    | Password Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de seguridad para contraseñas.
    |
    */

    // Número de contraseñas anteriores que no se pueden reutilizar
    // Nota: Las contraseñas de más de 1 año se pueden reutilizar
    'password_history_limit' => (int) env('PASSWORD_HISTORY_LIMIT', 12),

    // Días hasta que la contraseña expire y deba ser cambiada
    'password_expiration_days' => (int) env('PASSWORD_EXPIRATION_DAYS', 30),

    // Días de advertencia antes de que expire (mostrar mensaje al usuario)
    'password_expiration_warning_days' => (int) env('PASSWORD_EXPIRATION_WARNING_DAYS', 5),
];
