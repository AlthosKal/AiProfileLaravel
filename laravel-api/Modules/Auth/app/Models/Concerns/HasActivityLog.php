<?php

namespace Modules\Auth\Models\Concerns;

use Spatie\Activitylog\LogOptions;

/**
 * Configuración de auditoría con Spatie ActivityLog para el modelo User.
 *
 * Registra cambios en los campos más sensibles del usuario para trazabilidad,
 * excluyendo datos que no deben aparecer en logs (password hash, tokens).
 */
trait HasActivityLog
{
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // Campos a auditar (NO incluir password hash por seguridad)
            ->logOnly([
                'name',
                'email',
                'is_email_verified',
                'email_verified_at',
                'password_changed_at',
                'security_status',
            ])
            ->logOnlyDirty() // Solo cambios reales
            ->dontSubmitEmptyLogs() // No crear logs vacíos
            ->setDescriptionForEvent(fn (string $eventName) => "Usuario $eventName")
            ->useLogName('user') // Nombre del log para filtrar
            ->dontLogIfAttributesChangedOnly(['remember_token', 'updated_at']);
    }
}
