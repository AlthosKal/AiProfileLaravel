<?php

namespace Modules\Auth\Security;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

/**
 * Genera JWTs RS256 de corta duración para comunicación interna entre
 * laravel-api y laravel-mcp.
 *
 * El token se firma con la clave privada de Passport y puede ser validado
 * en laravel-mcp usando únicamente la clave pública (PASSPORT_PUBLIC_KEY).
 * No escribe nada en base de datos — es completamente stateless.
 *
 * El JWT generado se cachea en Redis durante su tiempo de vida (5 minutos)
 * para evitar regenerarlo en cada request del mismo usuario.
 *
 * Claims incluidos:
 *   - sub: email del usuario autenticado
 *   - iss: URL de laravel-api (identifica el emisor)
 *   - iat: timestamp de emisión
 *   - exp: timestamp de expiración (5 minutos)
 */
readonly class InternalJwtSecurity
{
    private const TTL_SECONDS = 300;

    private const CACHE_PREFIX = 'internal_jwt:';

    private Configuration $jwtConfiguration;

    public function __construct()
    {
        $this->jwtConfiguration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText(config('passport.private_key')),
            InMemory::plainText('empty'),
        );
    }

    /**
     * Retorna un JWT interno válido para el email dado.
     *
     * Si ya existe uno en caché para ese email, lo reutiliza.
     * Si no, genera uno nuevo, lo almacena en Redis con TTL de 5 minutos
     * y lo retorna. El TTL del caché se alinea con la expiración del token
     * para que nunca se sirva un token expirado desde caché.
     */
    public function forEmail(string $email): string
    {
        $cacheKey = self::CACHE_PREFIX.sha1($email);

        return Cache::store('redis')->remember(
            $cacheKey,
            self::TTL_SECONDS,
            fn () => $this->generate($email),
        );
    }

    /**
     * Genera un JWT interno firmado con RS256 para el email dado.
     */
    private function generate(string $email): string
    {
        $now = new DateTimeImmutable;

        return $this->jwtConfiguration->builder()
            ->issuedBy(config('app.url'))
            ->issuedAt($now)
            ->expiresAt($now->modify(sprintf('+%d seconds', self::TTL_SECONDS)))
            ->relatedTo($email)
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey())
            ->toString();
    }
}
