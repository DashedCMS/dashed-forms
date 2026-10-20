<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dashed__form_reply_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('subject')->nullable();
            $table->json('body')->nullable();
            $table->json('form_ids')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__form_reply_templates');
    }
};
