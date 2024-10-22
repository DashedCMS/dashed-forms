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
            $table->json('webhooks')
                ->nullable();
        });

        foreach (\Dashed\DashedForms\Models\Form::all() as $form) {
            if ($form->webhook_url && $form->webhook_class) {
                $form->webhooks = [
                    [
                        'url' => $form->webhook_url,
                        'class' => $form->webhook_class,
                    ],
                ];
                $form->save();
            }
        }

        Schema::table('dashed__forms', function (Blueprint $table) {
            $table->dropColumn('webhook_url');
            $table->dropColumn('webhook_class');
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
