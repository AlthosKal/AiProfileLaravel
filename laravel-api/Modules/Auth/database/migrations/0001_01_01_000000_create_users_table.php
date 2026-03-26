<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Enums\IdentificationTypeEnum;
use Modules\Auth\Enums\SecurityStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Identificador único del usuario dentro del sistema');
            $table->string('name')->comment('Nombre del usuario');
            $table->string('email')->unique()->comment('Correo electrónico del usuario');
            $table->boolean('is_email_verified')->default(false)->comment('Indica si un usuario ya fue autenticado');
            $table->timestamp('email_verified_at')->nullable()->comment('Fecha en la que el usuario ha sido verificado');
            $table->string('password')->comment('Contraseña encriptada del usuario');
            $table->text('two_factor_secret')->nullable()->comment('Almacena la clave secreta encriptada (TOTP) que se comparte con la app authenticator (Google Authenticator, Authy, etc.) para generar los códigos de 6 dígitos');
            $table->text('two_factor_recovery_codes')->nullable()->comment('Almacena un array JSON de códigos de recuperación encriptados, usados cuando el usuario no tiene acceso a su app authenticator ');
            $table->boolean('is_two_factor_confirmed')->default(false)->comment('Indica si un usuario confirmó/activó el 2FA. Si es false, el 2FA está habilitado pero pendiente de confirmar');
            $table->timestamp('two_factor_confirmed_at')->nullable()->comment('Fecha de cuando el usuario confirmó/activó el 2FA');
            $table->boolean('google_auth_enabled')->default(true)->comment('Indica si el usuario puede autenticarse con Google');
            $table->rememberToken()->comment('Token para mantener la sesión activa entre visitas ("Recordarme")');
            $table->timestamp('password_changed_at')->nullable()->comment('Ultima fecha donde se cambió la contraseña');
            $table->integer('identification_number')->nullable()->comment('Número de identificación');
            $table->enum('identification_type', array_column(IdentificationTypeEnum::cases(), 'value'))->nullable()->comment('Tipo de identificación');
            $table->timestamp('last_login_at')->nullable()->comment('Ultimo login del usuario');
            $table->timestamp('last_logout_at')->nullable()->comment('Ultimo logout');

            // Sistema de Bloqueo de Seguridad (automático por intentos fallidos)
            // SOLO el estado - Los detalles están en user_security_events
            $table->enum('security_status', array_column(SecurityStatusEnum::cases(), 'value'))->default(SecurityStatusEnum::UNBLOCKED->value)->comment('Indica el estado de seguridad del usuario');

            $table->timestamps();
            // Índices para búsqueda y filtrado
            $table->index('identification_number');
            $table->index('identification_type');
            $table->index('last_login_at');
            $table->index('created_at');
            // Índice compuesto para búsquedas únicas por documento
            $table->index(['identification_type', 'identification_number'], 'users_id_type_number_idx');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('Correo electrónico del usuario que solicitó el restablecimiento de contraseña');
            $table->string('token')->comment('Token único y hasheado para validar la solicitud de restablecimiento');
            $table->timestamp('created_at')->nullable()->comment('Fecha y hora en que se generó el token de restablecimiento');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Identificador único de la sesión');
            $table->foreignUuid('user_id')->nullable()->index()->comment('Referencia al usuario autenticado; null para sesiones anónimas');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP desde la que se inició la sesión (soporta IPv4 e IPv6)');
            $table->text('user_agent')->nullable()->comment('User-Agent del navegador o cliente que creó la sesión');
            $table->longText('payload')->comment('Datos serializados de la sesión');
            $table->integer('last_activity')->index()->comment('Timestamp Unix de la última actividad registrada en la sesión');

            // Índice para auditoría por Ip
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
