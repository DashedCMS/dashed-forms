<?php

namespace Dashed\DashedForms\Commands;

use Illuminate\Console\Command;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedForms\Jobs\SyncFormInputApisJob;

class SendApisForFormInputs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:send-apis-for-form-inputs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reaper voor form-inputs waarvan de API-sync niet via de submit-job is gelukt; dispatcht SyncFormInputApisJob per achterblijver';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $formInputs = FormInput::where('should_send_api', 1)
            ->where('api_send', '!=', 1)
            ->pluck('id');

        foreach ($formInputs as $formInputId) {
            SyncFormInputApisJob::dispatch($formInputId);
            $this->info("Dispatched SyncFormInputApisJob for Form Input ID: {$formInputId}");
        }

        return 0;
    }
}
