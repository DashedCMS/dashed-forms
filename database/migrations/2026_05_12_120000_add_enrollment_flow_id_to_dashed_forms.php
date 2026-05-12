<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('dashed__forms', 'enrollment_flow_id')) {
            return;
        }

        Schema::table('dashed__forms', function (Blueprint $table) {
            // Nullable: existing forms are not enrolled in any flow. When set,
            // FormSubmitted listeners in dashed-marketing route the submitter
            // into the matching PopupFollowUpFlow.
            // FK guarded so a graceful skip is possible on hosts that haven't
            // installed dashed-popups yet (the table won't exist there).
            if (Schema::hasTable('dashed__popup_follow_up_flows')) {
                $table->foreignId('enrollment_flow_id')
                    ->nullable()
                    ->constrained('dashed__popup_follow_up_flows')
                    ->nullOnDelete();
            } else {
                $table->unsignedBigInteger('enrollment_flow_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__forms', 'enrollment_flow_id')) {
            return;
        }

        Schema::table('dashed__forms', function (Blueprint $table) {
            try {
                $table->dropForeign(['enrollment_flow_id']);
            } catch (\Throwable) {
                // No FK to drop (host without dashed-popups) — ignore.
            }
            $table->dropColumn('enrollment_flow_id');
        });
    }
};
