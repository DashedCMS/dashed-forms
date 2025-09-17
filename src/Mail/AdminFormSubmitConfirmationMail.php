<?php

namespace Dashed\DashedForms\Mail;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedForms\Models\Form;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

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
        $mail = $this->view(config('dashed-core.site_theme') . '.emails.admin-confirm-form-submit')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))->subject(Translation::get('admin-form-confirmation-' . Str::slug($this->form->name) . '-email-subject', 'forms', 'Het formulier :name: is ingevuld!', 'text', [
                'name' => $this->form->name,
            ]))
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
