<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Prullenbak: verwijderen zet deleted_at, en terugzetten kan tot iemand
        // het in de prullenbak definitief weggooit.
        if (! Schema::hasTable('dashed__forms') || Schema::hasColumn('dashed__forms', 'deleted_at')) {
            return;
        }

        Schema::table('dashed__forms', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__forms', 'deleted_at')) {
            return;
        }

        Schema::table('dashed__forms', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
