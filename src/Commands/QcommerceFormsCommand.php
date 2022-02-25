<?php

namespace Qubiqx\QcommerceForms\Commands;

use Illuminate\Console\Command;

class QcommerceFormsCommand extends Command
{
    public $signature = 'qcommerce-forms';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
