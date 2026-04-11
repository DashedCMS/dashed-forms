<?php

namespace Dashed\DashedForms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class FormInputsExportMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public string $hash;

    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }

    public static function emailTemplateName(): string
    {
        return 'Geëxporteerde formulier invoer';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden met een export van formulier invoer als bijlage.';
    }

    public static function defaultSubject(): string
    {
        return 'Geëxporteerde formulier invoer';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Je export staat klaar', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>In de bijlage vind je de geëxporteerde formulier invoer.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        return ['siteName' => Customsetting::get('site_name')];
    }

    public function build()
    {
        $context = ['siteName' => Customsetting::get('site_name')];
        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('exported-form-inputs-email-subject', 'orders', 'Geëxporteerde formulier invoer'),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.exported-form-inputs')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.exported-form-inputs'
                : 'dashed-forms::emails.exported-form-inputs';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject(Translation::get('exported-form-inputs-email-subject', 'orders', 'Geëxporteerde formulier invoer'))
                ->with([
                    'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                ]);
        }

        $mail->attachFromStorageDisk('public', 'dashed/tmp-exports/' . $this->hash . '/forms/form-data.xlsx', Customsetting::get('site_name') . ' - geëxporteerde formulier invoer.xlsx');

        return $mail;
    }
}
