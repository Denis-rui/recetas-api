<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Almacena los códigos de recuperación de contraseña de seis dígitos y las
     * autorizaciones temporales (token_recuperacion) para la aplicación móvil.
     * Separa este flujo de la tabla password_reset_tokens para permitir:
     * - Conteo atómico y persistente de intentos fallidos (máximo 5).
     * - Almacenamiento seguro del hash bcrypt del código numérico.
     * - Tiempos de expiración independientes (10 min código, 15 min token).
     * - Registro explícito de verificación, consumo de autorización e invalidación.
     */
    public function up(): void
    {
        Schema::create('recuperaciones_password', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('codigo_hash');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('codigo_expira_en');
            $table->timestamp('codigo_verificado_en')->nullable();
            $table->string('token_recuperacion_hash', 64)->nullable()->unique();
            $table->timestamp('token_expira_en')->nullable();
            $table->timestamp('usado_en')->nullable();
            $table->timestamp('invalidado_en')->nullable();
            $table->timestamps();

            $table->index(['email', 'invalidado_en', 'usado_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recuperaciones_password');
    }
};
