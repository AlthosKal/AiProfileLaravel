<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id()->comment('Identificador único del token de acceso personal');
            // Relación polimórfica hacia el modelo propietario del token (ej. User)
            $table->morphs('tokenable');
            $table->text('name')->comment('Nombre descriptivo del token para identificarlo (ej. "API Mobile", "CLI")');
            $table->string('token', 64)->unique()->comment('Hash SHA-256 del token; el valor real solo se muestra al momento de creación');
            $table->text('abilities')->nullable()->comment('Lista JSON de habilidades/permisos concedidos al token');
            $table->timestamp('last_used_at')->nullable()->comment('Última fecha y hora en que el token fue utilizado');
            $table->timestamp('expires_at')->nullable()->index()->comment('Fecha y hora de expiración del token; null significa que no expira');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
