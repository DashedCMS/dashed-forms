<?php

namespace Dashed\DashedForms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

class FormInputsExportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $hash;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $view = view()->exists(config('dashed-core.site_theme') . '.emails.exported-form-inputs') ? config('dashed-core.site_theme') . '.emails.exported-form-inputs' : 'dashed-forms::emails.exported-form-inputs';

        $mail = $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('exported-form-inputs-email-subject', 'orders', 'Geëxporteerde formulier invoeren'))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);

        $mail->attachFromStorageDisk('public', 'dashed/tmp-exports/' . $this->hash . '/forms/form-data.xlsx', Customsetting::get('site_name') . ' - geëxporteerde formulier invoeren.xlsx');

        return $mail;
    }
}
