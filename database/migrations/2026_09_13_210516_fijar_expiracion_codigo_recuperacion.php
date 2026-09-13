<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recuperaciones_password', function (Blueprint $table) {
            // El default explícito impide el ON UPDATE implícito del primer TIMESTAMP en MariaDB.
            $table->timestamp('codigo_expira_en')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recuperaciones_password', function (Blueprint $table) {
            $table->timestamp('codigo_expira_en')->useCurrent()->useCurrentOnUpdate()->change();
        });
    }
};
