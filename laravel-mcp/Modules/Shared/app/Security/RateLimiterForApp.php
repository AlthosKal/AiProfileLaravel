<?php

namespace Modules\Shared\Security;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Shared\Enums\SharedErrorCode;

/**
 * Safety net de rate limiting a nivel de ruta.
 *
 * Registra un named limiter y retorna el string de middleware en una sola llamada,
 * permitiendo su uso directo en la definición de rutas:
 *
 *   ->middleware(RateLimiterForApp::middleware('login'))
 *
 * Dos capas opcionales (OWASP / Securing Laravel):
 *   - $byIp:    protección contra DDoS/flood — usar siempre
 *   - $byEmail: protección contra credential stuffing con rotación de IPs
 *               solo para rutas que reciben email en el body
 */
class RateLimiterForApp
{
    public static function middleware(string $name, bool $byIp = true, bool $byEmail = false): string
    {
        RateLimiter::for($name, function (Request $request) use ($byIp, $byEmail) {
            $limits = [];

            if ($byIp) {
                $limits[] = Limit::perMinutes(
                    config('auth.safety_net.decay_minutes'),
                    config('auth.safety_net.max_attempts'),
                )->by($request->ip())
                    ->response(fn (Request $req, array $headers) => response()->json([
                        'success' => false,
                        'status' => SharedErrorCode::RateLimiterForAppForIdFired->value,
                        'timestamp' => now()->toIso8601String(),
                        'path' => $req->path(),
                    ], Response::HTTP_TOO_MANY_REQUESTS, $headers));
            }

            if ($byEmail) {
                $limits[] = Limit::perMinute(60)
                    ->by($request->string('email')->lower()->value())
                    ->response(fn (Request $req, array $headers) => response()->json([
                        'success' => false,
                        'status' => SharedErrorCode::RateLimiterForAppForEmailFired->value,
                        'timestamp' => now()->toIso8601String(),
                        'path' => $req->path(),
                    ], Response::HTTP_TOO_MANY_REQUESTS, $headers));
            }

            return $limits;
        });

        return 'throttle:'.$name;
    }
}
