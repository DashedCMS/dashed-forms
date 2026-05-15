<?php

namespace Dashed\DashedForms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedForms\Models\FormInput;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncFormInputApisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $formInputId)
    {
    }

    public function handle(): void
    {
        $formInput = FormInput::find($this->formInputId);
        if (! $formInput || ! $formInput->should_send_api || (int) $formInput->api_send === 1) {
            return;
        }

        $formInput->sendApis();
        $formInput->refresh();

        if ((int) $formInput->api_send === 1) {
            $formInput->viewed = 1;
            $formInput->save();
        }
    }
}
