<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__form_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_input_id')->index();
            $table->unsignedBigInteger('form_reply_template_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('to_email');
            $table->json('cc')->nullable();
            $table->string('subject', 500);
            $table->longText('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__form_replies');
    }
};
