<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Enums\SecurityEventTypeEnum;
use Modules\Auth\Enums\SecurityStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_security_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Identificador único del evento de seguridad');
            $table->string('user_email')->comment('Referencia al usuario sobre el cual ocurrió el evento');
            $eventTypes = array_merge(
                array_map(fn ($case) => $case->value, SecurityStatusEnum::cases()),
                array_map(fn ($case) => $case->value, SecurityEventTypeEnum::cases())
            );
            $table->enum('event_type', $eventTypes)->comment('Tipo de evento de seguridad');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP desde la que se originó el evento (soporta IPv4 e IPv6)');
            $table->text('reason')->comment('Razón por la cual se registró el evento');
            $table->timestamp('event_at')->comment('Fecha y hora en que ocurrió el evento');
            $table->timestamp('expires_at')->nullable()->comment('Fecha y hora en la que expira el bloqueo temporal; null si el bloqueo es permanente o el evento no es un bloqueo');
            $table->integer('lockout_count_at_time')->default(0)->comment('Número acumulado de bloqueos del usuario en el momento del evento');
            $table->json('metadata')->nullable()->comment('Datos adicionales del evento: user_agent, detalles, etc.');

            $table->timestamps();

            // Foreign keys
            $table->foreign('user_email')->references('email')->on('users')->onDelete('cascade');

            // Índices básicos para consultas frecuentes
            $table->index('user_email');
            $table->index('event_type');
            $table->index('event_at');
            $table->index('ip_address');

            // Índice compuesto para consultas de eventos por usuario
            $table->index(['user_email', 'event_type', 'event_at'], 'user_security_events_user_type_date_idx');
        });
        // Índices especiales solo en PostgreSQL (sintaxis no compatible con SQLite)
        if (DB::getDriverName() === 'pgsql') {
            // Índice para obtener último evento por usuario (para la vista)
            DB::statement('CREATE INDEX user_security_events_user_latest_idx ON user_security_events (user_email, event_type, event_at DESC)');

            // Índice parcial para conteo de bloqueos (solo eventos de bloqueo)
            DB::statement("CREATE INDEX user_security_events_user_blocks_idx ON user_security_events (user_email, event_type) WHERE event_type IN ('temporarily_blocked', 'permanently_blocked')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_security_events');
    }
};
