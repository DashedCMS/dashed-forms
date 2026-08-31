<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__forms', function (Blueprint $table) {
            // Nullable: leeg betekent het standaard onderwerp uit de
            // e-mailtemplate of de ingebouwde fallback-tekst.
            if (! Schema::hasColumn('dashed__forms', 'customer_mail_subject')) {
                $table->string('customer_mail_subject')->nullable();
            }
            if (! Schema::hasColumn('dashed__forms', 'admin_mail_subject')) {
                $table->string('admin_mail_subject')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__forms', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__forms', 'customer_mail_subject')) {
                $table->dropColumn('customer_mail_subject');
            }
            if (Schema::hasColumn('dashed__forms', 'admin_mail_subject')) {
                $table->dropColumn('admin_mail_subject');
            }
        });
    }
};
