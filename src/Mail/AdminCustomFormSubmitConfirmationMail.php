<?php

namespace Qubiqx\QcommerceForms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceForms\Models\FormInput;
use Qubiqx\QcommerceTranslations\Models\Translation;

class AdminCustomFormSubmitConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public FormInput $formInput;
    public string $replyToEmail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(FormInput $formInput, string $replyToEmail = '')
    {
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
        $mail = $this->view('qcommerce-forms::emails.admin-custom-confirm-form-submit')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))->subject(Translation::get('admin-form-confirmation-'.Str::slug($this->formInput->form->name).'-email-subject', 'forms', 'You received a new form submit!'))
            ->with([
                'formInput' => $this->formInput,
            ]);

        if ($this->replyToEmail) {
            $mail->replyTo($this->replyToEmail);
        }

        return $mail;
    }
}
