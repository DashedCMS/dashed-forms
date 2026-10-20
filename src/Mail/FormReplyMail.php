<?php

declare(strict_types=1);

namespace Dashed\DashedForms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

/**
 * Antwoord op een formulierinzending, verstuurd naar de inzender. De tekst
 * zelf komt van de medewerker (los getypt, uit een formulier-template of van
 * de AI); de e-mailtemplate 'Antwoord op formulier aanvraag' levert de
 * huisstijl eromheen. Zonder e-mailtemplate valt de mail terug op een kale
 * opmaak.
 */
class FormReplyMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public FormInput $formInput,
        public string $body,
        public string $subjectLine,
    ) {
    }

    public static function emailTemplateName(): string
    {
        return 'Antwoord op formulier aanvraag';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de inzender als een medewerker een aanvraag beantwoordt vanuit het CMS. De variabele :replyContent: bevat het antwoord zelf.';
    }

    public static function availableVariables(): array
    {
        return ['replyContent', 'formName', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Reactie op je aanvraag via :formName:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'text', 'data' => ['body' => ':replyContent:']],
        ];
    }

    public static function sampleData(): array
    {
        $formInput = FormInput::query()->latest()->first();

        return [
            'formInput' => $formInput,
            'replyContent' => '<p>Beste klant,</p><p>Bedankt voor je bericht. We hebben je vraag bekeken en komen hier vandaag nog bij je op terug.</p><p>Met vriendelijke groet,<br>Het team van ' . Customsetting::get('site_name') . '</p>',
            'formName' => $formInput?->form?->name ?? 'Contactformulier',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $formInput = FormInput::query()->latest()->first();

        if (! $formInput) {
            return null;
        }

        return new self($formInput, self::sampleData()['replyContent'], 'Reactie op je aanvraag');
    }

    public function build()
    {
        // Afzender gescoped op de site van de inzending, niet de globale
        // MAIL_FROM-default: zo komt het antwoord van bijv. klantenservice@...
        $siteId = $this->formInput->site_id;
        $fallbackFromEmail = Customsetting::get('site_from_email', $siteId);
        $fallbackFromName = (string) Customsetting::get('site_name', $siteId);
        $locale = $this->formInput->locale ?: app()->getLocale();

        $context = [
            'formInput' => $this->formInput,
            'replyContent' => $this->body,
            'formName' => $this->formInput->form?->name ?? '',
            'siteName' => $fallbackFromName,
        ];

        $templateHtml = $this->renderFromTemplate($context, $locale);

        if ($templateHtml !== null) {
            [$fromEmail, $fromName] = $this->templateFrom($fallbackFromEmail, $fallbackFromName, $locale);
            $mail = $this->html($templateHtml)->subject($this->subjectLine);

            return $fromEmail ? $mail->from($fromEmail, $fromName) : $mail;
        }

        $html = '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:15px;line-height:1.6;color:#111827;max-width:620px">'
            . $this->body
            . '</div>';

        $mail = $this->html($html)->subject($this->subjectLine);

        return $fallbackFromEmail ? $mail->from($fallbackFromEmail, $fallbackFromName) : $mail;
    }
}
