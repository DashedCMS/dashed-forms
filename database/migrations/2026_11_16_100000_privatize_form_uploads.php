<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedForms\Jobs\PrivatizeFormUploads;
use Dashed\DashedCore\Security\Uploads\PrivateUploads;

/**
 * Bestaande formulierbestanden naar de privé opslag (dashed-core
 * PrivateUploads). Het verhuizen zelf gebeurt in een queued job, zodat een
 * deploy niet wacht op het kopiëren van honderden bestanden. Alleen in
 * productie (dashed-core.uploads.privatize_existing_on_migrate): lokaal draait
 * deze migratie vaak op een kopie van de productiedatabase met de echte
 * DigitalOcean-disk erachter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__form_input_fields') || ! PrivateUploads::privatizeOnMigrate()) {
            return;
        }

        rescue(fn () => PrivatizeFormUploads::dispatch(), null, true);
    }

    public function down(): void
    {
        // Bestanden gaan niet terug naar een publieke map.
    }
};
