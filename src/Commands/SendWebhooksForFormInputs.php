<?php

namespace Dashed\DashedForms\Commands;

use Illuminate\Console\Command;
use Dashed\DashedForms\Models\FormInput;

class SendWebhooksForFormInputs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:send-webhooks-for-form-inputs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send webhooks for form inputs that have not been sent yet';

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
        $formInputs = FormInput::where('should_send_webhook', 1)
            ->where('webhook_send', '!=', 1)
            ->get();

        foreach ($formInputs as $formInput) {
            $formInput->sendWebhooks();
            $formInput->refresh();
            if ($formInput->webhook_send == 1) {
                $this->info("Webhooks sent for Form Input ID: {$formInput->id}");
                $formInput->viewed = 1;
                $formInput->save();
            } else {
                $this->error("Failed to send Webhooks for Form Input ID: {$formInput->id}");
            }
        }

        return 0;
    }
}
