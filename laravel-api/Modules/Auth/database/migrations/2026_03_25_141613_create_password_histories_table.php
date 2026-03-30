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
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->string('user_email');
            $table->foreign('user_email')->references('email')->on('users')->cascadeOnDelete();
            $table->string('password')->comment('Contraseña prevía del usuario registrado en el sistema');

            $table->timestamps();

            // Index para búsquedas
            $table->index(['user_email', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
