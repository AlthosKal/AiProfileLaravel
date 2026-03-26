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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id()->comment('Identificador único del job en cola');
            $table->string('queue')->index()->comment('Nombre de la cola a la que pertenece el job');
            $table->longText('payload')->comment('Datos serializados del job, incluyendo la clase y sus parámetros');
            $table->unsignedTinyInteger('attempts')->comment('Número de intentos de ejecución realizados');
            $table->unsignedInteger('reserved_at')->nullable()->comment('Timestamp Unix de cuando el job fue tomado por un worker; null si está disponible');
            $table->unsignedInteger('available_at')->comment('Timestamp Unix desde el cual el job puede ser procesado');
            $table->unsignedInteger('created_at')->comment('Timestamp Unix de cuando el job fue agregado a la cola');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Identificador único del lote de jobs');
            $table->string('name')->comment('Nombre descriptivo del lote asignado al crearlo');
            $table->integer('total_jobs')->comment('Número total de jobs que componen el lote');
            $table->integer('pending_jobs')->comment('Número de jobs aún pendientes de procesarse');
            $table->integer('failed_jobs')->comment('Número de jobs que fallaron durante el procesamiento');
            $table->longText('failed_job_ids')->comment('Lista JSON de los IDs de los jobs fallidos');
            $table->mediumText('options')->nullable()->comment('Opciones serializadas del lote (callbacks, etc.)');
            $table->integer('cancelled_at')->nullable()->comment('Timestamp Unix de cuando el lote fue cancelado; null si no fue cancelado');
            $table->integer('created_at')->comment('Timestamp Unix de cuando se creó el lote');
            $table->integer('finished_at')->nullable()->comment('Timestamp Unix de cuando el lote terminó su procesamiento; null si aún no ha finalizado');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id()->comment('Identificador único del job fallido');
            $table->string('uuid')->unique()->comment('UUID único del job para identificarlo de forma global');
            $table->text('connection')->comment('Nombre de la conexión de cola usada al ejecutar el job');
            $table->text('queue')->comment('Nombre de la cola en la que estaba el job cuando falló');
            $table->longText('payload')->comment('Datos serializados del job al momento del fallo');
            $table->longText('exception')->comment('Traza completa de la excepción que causó el fallo');
            $table->timestamp('failed_at')->useCurrent()->comment('Fecha y hora en que el job falló');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
