<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea una vista optimizada para consultar el estado de seguridad actual de usuarios
     * sin necesidad de tener campos redundantes en la tabla users.
     *
     * Performance: Con los índices correctos declarados en los modelos de donde se extraen los campos para armar esta vista, la consulta es tan rápida como
     * consultar campos directamente en la tabla users.
     */
    public function up(): void
    {
        // LATERAL JOIN es exclusivo de PostgreSQL — la vista no se crea en SQLite (tests)
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            CREATE VIEW user_security_state AS
            SELECT
                u.email AS user_email,
                u.name,
                u.security_status,
                -- Detalles del último evento de bloqueo
                latest_block.reason as blocked_reason,
                latest_block.event_at as blocked_at,
                latest_block.expires_at as blocked_until,
                latest_block.ip_address as blocked_from_ip,
                -- Contador total de bloqueos
                COALESCE(block_counts.lockout_count, 0) as lockout_count
            FROM users u
            -- Obtener el último evento de bloqueo (LATERAL JOIN optimizado con índice)
            LEFT JOIN LATERAL (
                SELECT
                    reason,
                    event_at,
                    expires_at,
                    ip_address
                FROM user_security_events
                WHERE user_email = u.email
                  AND event_type IN ('temporarily_blocked', 'permanently_blocked')
                ORDER BY event_at DESC
                LIMIT 1
            ) latest_block ON true
            -- Contar total de bloqueos (usa índice parcial)
            LEFT JOIN (
                SELECT
                    user_email,
                    COUNT(*) as lockout_count
                FROM user_security_events
                WHERE event_type IN ('temporarily_blocked', 'permanently_blocked')
                GROUP BY user_email
            ) block_counts ON block_counts.user_email = u.email;
        ");

        // NOTA: Las vistas en PostgreSQL NO pueden tener índices.
        // Esta vista usa automáticamente los índices de las tablas base:
        // - users(email, security_status, is_active)
        // - user_security_events(user_email, event_type, event_at DESC)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS user_security_state');
    }
};
