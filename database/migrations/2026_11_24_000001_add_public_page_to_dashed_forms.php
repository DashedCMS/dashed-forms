<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * A form can get its own public page (/aanmelden/{slug}) so it can be
     * shared without building a page around it, and can close itself after
     * a deadline or a maximum number of submissions.
     */
    private array $columns = [
        'is_public',
        'slug',
        'public_title',
        'public_intro',
        'public_image',
        'closes_at',
        'max_submissions',
        'closed_message',
    ];

    public function up(): void
    {
        Schema::table('dashed__forms', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__forms', 'is_public')) {
                $table->boolean('is_public')->default(false);
            }
            if (! Schema::hasColumn('dashed__forms', 'slug')) {
                $table->string('slug')->nullable()->unique();
            }
            if (! Schema::hasColumn('dashed__forms', 'public_title')) {
                $table->string('public_title')->nullable();
            }
            if (! Schema::hasColumn('dashed__forms', 'public_intro')) {
                $table->longText('public_intro')->nullable();
            }
            if (! Schema::hasColumn('dashed__forms', 'public_image')) {
                $table->string('public_image')->nullable();
            }
            if (! Schema::hasColumn('dashed__forms', 'closes_at')) {
                $table->dateTime('closes_at')->nullable();
            }
            if (! Schema::hasColumn('dashed__forms', 'max_submissions')) {
                $table->unsignedInteger('max_submissions')->nullable();
            }
            if (! Schema::hasColumn('dashed__forms', 'closed_message')) {
                $table->text('closed_message')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__forms', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__forms', 'slug')) {
                $table->dropUnique(['slug']);
            }
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('dashed__forms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
