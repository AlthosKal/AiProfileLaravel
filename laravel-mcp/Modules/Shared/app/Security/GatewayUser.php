<?php

namespace Modules\Shared\Security;

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
final readonly class GatewayUser implements Authenticatable
{
    public function __construct(
        public string $email,
    ) {}

    /**
     * Nombre del campo que identifica al usuario.
     * Laravel lo usa internamente para saber qué propiedad buscar en el provider.
     * Aquí siempre es 'email' porque no existe tabla de usuarios en laravel-mcp.
     */
    public function getAuthIdentifierName(): string
    {
        return 'email';
    }

    /**
     * Valor del identificador del usuario autenticado.
     *
     * Este es el método que debes usar para obtener el email en cualquier
     * parte del sistema. Sin embargo, la forma idiomática de Laravel es:
     *
     *   $request->user()->email
     *
     * Ambas expresiones retornan exactamente lo mismo.
     */
    public function getAuthIdentifier(): string
    {
        return $this->email;
    }

    /**
     * No aplica — GatewayUser no tiene contraseña propia.
     * La autenticación ocurre en laravel-api; aquí solo se valida el JWT.
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * No aplica — GatewayUser no tiene contraseña propia.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * No aplica — las sesiones no se usan en esta API interna stateless.
     */
    public function getRememberToken(): string
    {
        return '';
    }

    /**
     * No aplica — las sesiones no se usan en esta API interna stateless.
     */
    public function setRememberToken(mixed $value): void {}

    /**
     * No aplica — las sesiones no se usan en esta API interna stateless.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
