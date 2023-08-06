<?php

namespace Dashed\DashedForms\Mail;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedForms\Models\Form;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class FormSubmitConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Form $form, FormInput $formInput)
    {
        $this->form = $form;
        $this->formInput = $formInput;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('dashed-forms::emails.confirm-form-submit')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))->subject(Translation::get('form-confirmation-'.Str::slug($this->form->name).'-email-subject', 'forms', 'We received your form submit!'))
            ->with([
                'form' => $this->form,
                'formInput' => $this->formInput,
            ]);
    }
}
