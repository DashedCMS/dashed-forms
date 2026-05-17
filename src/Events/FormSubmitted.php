<?php

namespace Dashed\DashedForms\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

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

    public ?int $site_id;

    public function __construct(
        public int $form_id,
        public int $form_input_id,
        public ?string $email,
        public string $locale,
        int|string|null $site_id = null,
    ) {
        // Defensive coercion: site_id may arrive as a string from Eloquent
        // attributes that have no cast, from request payloads, or from
        // multi-site config helpers. Normalise to int|null so listeners can
        // rely on the typed property.
        $this->site_id = ($site_id === null || $site_id === '') ? null : (int) $site_id;
    }
}
