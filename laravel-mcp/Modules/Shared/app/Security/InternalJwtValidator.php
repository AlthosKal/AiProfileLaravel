<?php

namespace Modules\Shared\Security;

use DateTimeZone;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Throwable;

/**
 * Valida JWTs RS256 internos emitidos por laravel-api fuera del ciclo HTTP.
 *
 * En requests HTTP la validación ya la realiza JwtGatewayGuard a través del
 * middleware auth:api — esta clase no es necesaria ahí.
 *
 * Su uso está orientado a contextos donde no existe un Request de Laravel:
 * consumers de Kafka, jobs en cola, comandos Artisan, etc. En esos contextos
 * el JWT viaja en el payload del mensaje y debe ser validado manualmente.
 *
 * Aplica las mismas reglas que JwtGatewayGuard:
 *   - Firma RS256 verificada con PASSPORT_PUBLIC_KEY
 *   - Token no expirado (exp claim)
 *   - Emisor correcto (iss = laravel-api URL)
 */
readonly class InternalJwtValidator
{
    /**
     * Válida el JWT y retorna el email del subject si es válido.
     * Retorna null si el token es inválido, expirado o mal firmado.
     */
    public function validate(string $token): ?string
    {
        try {
            $config = $this->buildConfiguration();

            $parsed = $config->parser()->parse($token);
            assert($parsed instanceof Plain);

            $config->validator()->assert(
                $parsed,
                new SignedWith($config->signer(), $config->verificationKey()),
                new LooseValidAt(new SystemClock(new DateTimeZone('UTC'))),
                new IssuedBy(config('app.internal_api_url')),
            );

            $email = $parsed->claims()->get('sub');

            return ! empty($email) ? $email : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function buildConfiguration(): Configuration
    {
        return Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText('empty'),
            InMemory::plainText(config('passport.public_key')),
        );
    }
}
