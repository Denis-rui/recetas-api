<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_perfil', 2048)->nullable();
            $table->enum('rol', ['usuario', 'administrador'])
                ->default('usuario');
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'foto_perfil',
                'rol',
                'activo',
            ]);
        });
    }
};
