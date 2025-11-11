<?php

namespace Dashed\DashedForms\Commands;

use Illuminate\Console\Command;
use Dashed\DashedForms\Models\FormInput;

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
    protected $description = 'Send apis for form inputs that have not been sent yet';

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
            ->get();

        foreach ($formInputs as $formInput) {
            $formInput->sendApis();
            $formInput->refresh();
            if ($formInput->api_send == 1) {
                $this->info("APIs sent for Form Input ID: {$formInput->id}");
                $formInput->viewed = 1;
                $formInput->save();
            } else {
                $this->error("Failed to send APIs for Form Input ID: {$formInput->id}");
            }
        }

        return 0;
    }
}
