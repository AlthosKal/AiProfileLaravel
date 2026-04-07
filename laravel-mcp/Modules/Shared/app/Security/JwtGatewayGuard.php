<?php

namespace Modules\Shared\Security;

use DateTimeZone;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
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
 * Guard que autentica requests en laravel-mcp validando el JWT RS256
 * emitido por laravel-api.
 *
 * Proceso de validación por orden:
 *   1. Extraer el Bearer token del header Authorization
 *   2. Parsear el JWT con lcobucci/jwt
 *   3. Verificar la firma RS256 con PASSPORT_PUBLIC_KEY
 *   4. Verificar que el token no haya expirado (exp claim)
 *   5. Verificar que el emisor sea laravel-api (iss claim)
 *   6. Extraer el email del claim sub y construir GatewayUser en memoria
 *
 * Si cualquier paso falla, user() retorna null y el middleware auth:jwt-gateway
 * responde con 401 automáticamente.
 */
final class JwtGatewayGuard implements Guard
{
    private ?GatewayUser $user = null;

    public function __construct(private readonly Request $request) {}

    /**
     * Construye la configuración JWT de forma lazy para que en tests
     * el config() ya esté sobrescrito cuando se llama a user().
     */
    private function jwtConfiguration(): Configuration
    {
        return Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText('empty'),
            InMemory::plainText(config('passport.public_key')),
        );
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?GatewayUser
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if (empty($token)) {
            return null;
        }

        try {
            $jwtConfiguration = $this->jwtConfiguration();
            $parsed = $jwtConfiguration->parser()->parse($token);

            $jwtConfiguration->validator()->assert($parsed, new SignedWith($jwtConfiguration->signer(), $jwtConfiguration->verificationKey()), new LooseValidAt(new SystemClock(new DateTimeZone('UTC'))), new IssuedBy(config('app.internal_api_url'))

            );

            if (! $parsed instanceof Plain) {
                return null;
            }

            $email = $parsed->claims()->get('sub');

            if (empty($email)) {
                return null;
            }

            $this->user = new GatewayUser(email: $email);
        } catch (Throwable) {
            return null;
        }

        return $this->user;
    }

    public function id(): ?string
    {
        $this->user();

        return $this->user?->email;
    }

    /** @param array<string, mixed> $credentials */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user instanceof GatewayUser ? $user : null;

        return $this;
    }
}
