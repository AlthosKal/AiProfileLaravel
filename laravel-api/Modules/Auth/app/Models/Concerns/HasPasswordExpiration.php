<?php

namespace Modules\Auth\Models\Concerns;

use Hash;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Auth\Models\PasswordHistory;

/**
 * Funcionalidad de expiración de contraseña para el modelo User.
 *
 * Centraliza la lógica de vencimiento basada en `password_changed_at`
 * y la configuración `auth.password_expiration_days`.
 *
 * @property Carbon|null $password_changed_at
 */
trait HasPasswordExpiration
{
    /**
     * Verificar si el usuario cuenta con una contraseña
     */
    public function isPasswordExists(): bool
    {
        return ! empty($this->password);
    }

    /**
     * Obtener la fecha en la cual se hizo el último cambio de contraseña
     */
    public function getPasswordTimeExists(): Carbon
    {
        $expirationDays = config('auth.password_expiration_days', 30);

        if (! $this->password_changed_at) {
            return now()->addDays($expirationDays);
        }

        return $this->password_changed_at->addDays($expirationDays);
    }

    /**
     * Verificar si la contraseña actual ya venció.
     *
     * Si nunca se ha cambiado la contraseña (`password_changed_at` es null),
     * se considera que no ha expirado para no bloquear el primer acceso.
     */
    public function hasPasswordExpired(): bool
    {
        return $this->getPasswordTimeExists()->isPast();
    }

    /**
     * Obtener los días restantes hasta que venza la contraseña.
     *
     * Si nunca se ha cambiado la contraseña, retorna el total de días
     * configurados como si acabara de ser establecida hoy.
     */
    public function getDaysUntilPasswordExpires(): int
    {
        $expirationDate = $this->getPasswordTimeExists();

        // Cast explícito a int para evitar warning de conversión implícita
        return (int) max(0, now()->diffInDays($expirationDate));

    }

    public function isPasswordAboutToExpire(): bool
    {
        $warningDays = config('auth.password_expiration_warning_days', 5);
        $daysLeft = $this->getDaysUntilPasswordExpires();

        return $daysLeft > 0 && $daysLeft <= $warningDays;
    }

    /**
     * @return HasMany<PasswordHistory, $this>
     */
    public function getPasswordHistories(): HasMany
    {
        return $this->passwordHistories()
            ->orderByDesc('created_at')
            ->limit(config('auth.password_history_limit', 12));
    }

    /**
     * Eliminar entradas antiguas del historial manteniendo solo las últimas N.
     *
     * El límite por defecto es 12 para cubrir un año de cambios mensuales,
     * lo que es suficiente para validar reutilización de contraseñas recientes.
     */
    public function cleanOldPasswordHistories(): void
    {
        // Obtener los IDs a conservar (los $keep más recientes) y eliminar el resto.
        // Se usa limit+offset en lugar de skip() solo porque SQLite (tests) no soporta
        // OFFSET sin LIMIT; el comportamiento es idéntico en producción con MySQL/Postgres.
        $keepIds = $this->getPasswordHistories()
            ->pluck('id');

        $this->passwordHistories()
            ->when($keepIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $keepIds))
            ->delete();
    }

    public function verifyPasswordHistories(string $value): bool
    {
        return $this->getPasswordHistories()
            ->select('password')
            ->get()
            ->some(fn ($history) => Hash::check($value, $history->password));
    }
}
