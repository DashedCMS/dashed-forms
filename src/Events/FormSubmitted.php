<?php

namespace Dashed\DashedForms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired right after a form input row is persisted in either submit path
 * (Livewire Form::submit() or FormController::store()). Listeners hook
 * into this event to enrol the submitter in marketing flows, push to
 * external lead-management systems, etc.
 */
class FormSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $form_id,
        public int $form_input_id,
        public ?string $email,
        public string $locale,
        public ?int $site_id = null,
    ) {}
}
