<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Representa al usuario autenticado en laravel-mcp a partir de un JWT interno.
 *
 * No está respaldado por una tabla de base de datos. Se construye en memoria
 * con los claims extraídos del JWT RS256 emitido por laravel-api.
 *
 * Implementar Authenticatable permite integrarlo con el sistema de guards
 * de Laravel y exponer $request->user() en controllers y políticas.
 *
 * En el futuro, agregar claims adicionales al JWT (roles, permisos, etc.)
 * solo requiere extender el constructor de esta clase y el JwtGatewayGuard.
 */
final class GatewayUser implements Authenticatable
{
    public function __construct(
        public readonly string $email,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'email';
    }

    public function getAuthIdentifier(): string
    {
        return $this->email;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken(mixed $value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
