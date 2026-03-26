<?php

namespace Modules\Auth\Builders;

use Illuminate\Database\Eloquent\Builder;
use Modules\Auth\Models\UserSecurityState;

/**
 * Custom Query Builder para UserCurrentSecurityState
 *
 * Este builder permite encadenar métodos con tipado correcto para PHPStan
 * sin necesidad de usar {@method} en el modelo.
 *
 * @extends Builder<UserSecurityState>
 */
class UserSecurityStateBuilder extends Builder
{
    /**
     * JOIN con tabla users para obtener created_at/updated_at
     *
     * La vista no incluye timestamps, así que hacemos JOIN
     */
    public function withTimestamps(): self
    {
        $this->join('users', 'users.id', '=', 'user_current_security_state.user_id')
            ->select('user_current_security_state.*', 'users.created_at', 'users.updated_at');

        return $this;
    }

    /**
     * Ordenar por más recientes primero
     *
     * Asume que ya se hizo el JOIN con users
     */
    public function orderByRecent(): self
    {
        $this->orderBy('users.created_at', 'desc');

        return $this;
    }

    /**
     * Buscar por nombre o email
     */
    public function search(string $term): self
    {
        $this->where(function ($q) use ($term) {
            $q->where('user_current_security_state.name', 'like', "%$term%")
                ->orWhere('user_current_security_state.email', 'like', "%$term%");
        });

        return $this;
    }

    /**
     * Solo usuarios bloqueados (temporal o permanente)
     */
    public function onlyBlocked(): self
    {
        $this->whereIn('security_status', ['temporarily_blocked', 'permanently_blocked']);

        return $this;
    }

    /**
     * Solo bloqueos temporales expirados
     */
    public function expiredBlocks(): self
    {
        $this->where('security_status', 'temporarily_blocked')
            ->where('blocked_until', '<=', now());

        return $this;
    }

    /**
     * Obtener estadísticas de seguridad de usuarios
     *
     * Query única que obtiene todos los contadores en una sola consulta.
     * Útil para dashboards administrativos.
     *
     * @return array<string, int|float>
     */
    public function getSecurityStats(): array
    {
        $stats = $this->selectRaw('
            COUNT(*) as total_users,
            COUNT(CASE WHEN security_status = \'normal\' THEN 1 END) as normal_users,
            COUNT(CASE WHEN security_status = \'temporarily_blocked\' THEN 1 END) as temp_blocked_users,
            COUNT(CASE WHEN security_status = \'permanently_blocked\' THEN 1 END) as perm_blocked_users,
            COUNT(CASE WHEN is_active = false THEN 1 END) as manually_deactivated_users,
            AVG(lockout_count) as avg_lockout_count
        ')->first();

        return [
            'total_users' => (int) ($stats->total_users ?? 0),
            'normal_users' => (int) ($stats->normal_users ?? 0),
            'temp_blocked_users' => (int) ($stats->temp_blocked_users ?? 0),
            'perm_blocked_users' => (int) ($stats->perm_blocked_users ?? 0),
            'manually_deactivated_users' => (int) ($stats->manually_deactivated_users ?? 0),
            'avg_lockout_count' => round((float) ($stats->avg_lockout_count ?? 0), 2),
        ];
    }
}
