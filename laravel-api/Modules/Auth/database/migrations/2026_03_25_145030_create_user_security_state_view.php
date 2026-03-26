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
        DB::statement("
            CREATE VIEW user_security_state AS
            SELECT
                u.id as user_id,
                u.email,
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
                WHERE user_id = u.id
                  AND event_type IN ('temporary_block', 'permanent_block')
                ORDER BY event_at DESC
                LIMIT 1
            ) latest_block ON true
            -- Contar total de bloqueos (usa índice parcial)
            LEFT JOIN (
                SELECT
                    user_id,
                    COUNT(*) as lockout_count
                FROM user_security_events
                WHERE event_type IN ('temporary_block', 'permanent_block')
                GROUP BY user_id
            ) block_counts ON block_counts.user_id = u.id;
        ");

        // NOTA: Las vistas en PostgreSQL NO pueden tener índices.
        // Esta vista usa automáticamente los índices de las tablas base:
        // - users(id, security_status, is_active)
        // - user_security_events(user_id, event_type, event_at DESC)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS user_security_state');
    }
};
