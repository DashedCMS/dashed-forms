<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qcommerce__form_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_id')
                ->constrained('qcommerce__forms')
                ->cascadeOnDelete();
            $table->json('name');
            $table->boolean('required')
                ->default(false);
            $table->json('placeholder')
                ->nullable();
            $table->json('description')
                ->nullable();
            $table->json('helper_text');
            $table->string('type')
                ->default('input');
            $table->string('input_type')
                ->default('text');
            $table->json('options')
                ->nullable();
            $table->integer('sort')
                ->default(1);
            $table->json('images')
                ->nullable();
            $table->boolean('stack_start')
                ->default(0);
            $table->boolean('stack_end')
                ->default(0);

            $table->timestamps();
            $table->softDeletes();
        });


        Schema::table('qcommerce__form_inputs', function (Blueprint $table) {
            $table->longText('content')
                ->nullable()
                ->change();
        });

        Schema::create('qcommerce__form_input_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_input_id')
                ->constrained('qcommerce__form_inputs')
                ->cascadeOnDelete();

            $table->foreignId('form_field_id')
                ->constrained('qcommerce__form_fields')
                ->cascadeOnDelete();

            $table->longText('value');

            $table->timestamps();
        });

        Schema::table('qcommerce__forms', function (Blueprint $table) {
            $table->foreignId('email_confirmation_form_field_id')
                ->nullable()
                ->constrained('qcommerce__form_fields')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
