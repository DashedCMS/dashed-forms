<?php

namespace Dashed\DashedForms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dashed\DashedForms\Jobs\PrivatizeFormUploads;

class PrivatizeFormUploadsCommand extends Command
{
    protected $signature = 'dashed-forms:privatize-uploads
        {--now : Direct uitvoeren in plaats van via de queue}
        {--dry-run : Alleen tellen wat nog publiek staat}';

    protected $description = 'Verhuist formulierbestanden die nog publiek staan naar de privé opslag';

    public function handle(): int
    {
        $count = DB::table('dashed__form_input_fields')->where(function ($query) {
            foreach (array_keys(PrivatizeFormUploads::SOURCES) as $prefix) {
                $query->orWhere('value', 'like', $prefix . '%');
            }
        })->count();

        $this->info("{$count} formulierwaarde(n) wijzen nog naar een publiek bestand.");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $this->option('now') ? PrivatizeFormUploads::dispatchSync() : PrivatizeFormUploads::dispatch();
        $this->info($this->option('now') ? 'Klaar; zie de log voor de aantallen.' : 'In de queue gezet.');

        return self::SUCCESS;
    }
}
