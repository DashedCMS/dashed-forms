<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__forms', function (Blueprint $table) {
            $table->json('apis')
                ->nullable();
        });

        Schema::table('dashed__form_inputs', function (Blueprint $table) {
            $table->boolean('should_send_api')
                ->default(0);
            $table->boolean('api_send')
                ->default(0);
            $table->string('api_error')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            //
        });
    }
};
