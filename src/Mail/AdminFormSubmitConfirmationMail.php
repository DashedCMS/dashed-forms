<?php

namespace Qubiqx\QcommerceForms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceForms\Models\Form;
use Qubiqx\QcommerceForms\Models\FormInput;
use Qubiqx\QcommerceTranslations\Models\Translation;

class AdminFormSubmitConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Form $form, FormInput $formInput, $replyToEmail = null)
    {
        $this->form = $form;
        $this->formInput = $formInput;
        $this->replyToEmail = $replyToEmail;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this->view('qcommerce-forms::emails.admin-confirm-form-submit')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))->subject(Translation::get('admin-form-confirmation-'.Str::slug($this->form->name).'-email-subject', 'forms', 'You received a new form submit!'))
            ->with([
                'form' => $this->form,
                'formInput' => $this->formInput,
            ]);

        if ($this->replyToEmail) {
            $mail->replyTo($this->replyToEmail);
        }

        return $mail;
    }
}
