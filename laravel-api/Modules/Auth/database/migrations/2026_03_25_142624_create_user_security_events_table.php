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
            $table->id()->comment('Identificador único del evento de seguridad');
            $table->uuid('user_id')->comment('Referencia al usuario sobre el cual ocurrió el evento');
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
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Índices básicos para consultas frecuentes
            $table->index('user_id');
            $table->index('event_type');
            $table->index('event_at');
            $table->index('ip_address');

            // Índice compuesto para consultas de eventos por usuario
            $table->index(['user_id', 'event_type', 'event_at'], 'user_security_events_user_type_date_idx');
        });
        // Índices especiales con DB::statement (deben ejecutarse DESPUÉS de crear la tabla)

        // Índice para obtener último evento por usuario (para la vista)
        // Orden DESC en event_at para LIMIT 1 rápido
        DB::statement('CREATE INDEX user_security_events_user_latest_idx ON user_security_events (user_id, event_type, event_at DESC)');

        // Índice parcial para conteo de bloqueos (solo eventos de bloqueo)
        DB::statement("CREATE INDEX user_security_events_user_blocks_idx ON user_security_events (user_id, event_type) WHERE event_type IN ('temporary_block', 'permanent_block')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_security_events');
    }
};
