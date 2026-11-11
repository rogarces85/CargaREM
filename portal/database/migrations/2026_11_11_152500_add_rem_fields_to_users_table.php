<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos REM en la tabla de usuarios (D-001).
 *
 * `activo` existe porque un usuario nunca se elimina: se desactiva (R-ID-04).
 * `ultimo_acceso` alimenta la auditoría de acceso (R-ID-15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('password');
            $table->timestamp('ultimo_acceso')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activo', 'ultimo_acceso']);
        });
    }
};
