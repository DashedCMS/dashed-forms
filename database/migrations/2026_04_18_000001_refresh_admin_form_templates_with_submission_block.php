<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('dashed__email_templates')) {
            return;
        }

        $keys = [
            \Dashed\DashedForms\Mail\AdminFormSubmitConfirmationMail::class,
            \Dashed\DashedForms\Mail\AdminCustomFormSubmitConfirmationMail::class,
        ];

        $defaultBlocks = [
            ['type' => 'heading', 'data' => ['text' => 'Nieuw formulier ingediend', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Het formulier <strong>:formName:</strong> is ingevuld. Hieronder de ingevoerde gegevens:</p>']],
            ['type' => 'form-submission', 'data' => ['title' => 'Ingevoerde gegevens']],
        ];

        foreach ($keys as $key) {
            DB::table('dashed__email_templates')
                ->where('mailable_key', $key)
                ->update(['blocks' => json_encode($defaultBlocks)]);
        }
    }

    public function down(): void
    {
        // Non-destructive: leave the updated blocks in place.
    }
};
