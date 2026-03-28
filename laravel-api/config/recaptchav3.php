<?php

return [
    /*
|--------------------------------------------------------------------------
| reCAPTCHA v3 Configuration
|--------------------------------------------------------------------------
|
| Configuración para Google reCAPTCHA v3.
| Obtén tus claves desde: https://www.google.com/recaptcha/admin
|
*/
    'origin' => env('RECAPTCHAV3_ORIGIN', 'https://www.google.com/recaptcha'),
    'sitekey' => env('RECAPTCHAV3_SITEKEY', ''),
    'secret' => env('RECAPTCHAV3_SECRET', ''),
    'locale' => env('RECAPTCHAV3_LOCALE', ''),

    /*
|--------------------------------------------------------------------------
| Verification Settings
|--------------------------------------------------------------------------
|
| Configuración para la verificación de tokens de reCAPTCHA.
|
*/
    'timeout_seconds' => (int) env('RECAPTCHA_TIMEOUT_SECONDS', 5),
    'min_score' => env('RECAPTCHAV3_MINSCORE', 0.5),
];
