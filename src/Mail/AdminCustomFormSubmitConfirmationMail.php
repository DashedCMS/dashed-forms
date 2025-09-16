<?php

namespace Dashed\DashedForms\Mail;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

class AdminCustomFormSubmitConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public FormInput $formInput;
    public ?string $replyToEmail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(FormInput $formInput, ?string $replyToEmail = '')
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
        $mail = $this->view(config('dashed-core.site_theme') . '.emails.admin-custom-confirm-form-submit')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))->subject(Translation::get('admin-form-confirmation-'.Str::slug($this->formInput->form->name).'-email-subject', 'forms', 'You received a new form submit!'))
            ->with([
                'formInput' => $this->formInput,
            ]);

        if ($this->replyToEmail) {
            $mail->replyTo($this->replyToEmail);
        }

        return $mail;
    }
}
