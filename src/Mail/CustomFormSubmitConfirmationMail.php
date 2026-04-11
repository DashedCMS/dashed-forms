<?php

namespace Dashed\DashedForms\Mail;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class CustomFormSubmitConfirmationMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public FormInput $formInput;

    public string $replyToEmail;

    public function __construct(FormInput $formInput, string $replyToEmail = '')
    {
        $this->formInput = $formInput;
        $this->replyToEmail = $replyToEmail;
    }

    public static function emailTemplateName(): string
    {
        return 'Formulier bevestiging (custom, klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de indiener van een custom formulier als bevestiging.';
    }

    public static function availableVariables(): array
    {
        return ['formName', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'We hebben je aanvraag voor :formName: ontvangen!';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Bedankt voor je aanvraag!', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>We hebben je aanvraag voor het formulier <strong>:formName:</strong> in goede orde ontvangen. We nemen zo snel mogelijk contact met je op.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $formInput = FormInput::query()->latest()->first();

        return [
            'formInput' => $formInput,
            'formName' => $formInput?->form?->name ?? 'Contactformulier',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $formInput = FormInput::query()->latest()->first();

        return $formInput ? new self($formInput) : null;
    }

    public function build()
    {
        $context = [
            'formInput' => $this->formInput,
            'formName' => $this->formInput->form?->name ?? '',
            'siteName' => Customsetting::get('site_name'),
        ];

        $fallbackSubject = Translation::get('form-confirmation-' . Str::slug($this->formInput->form->name) . '-email-subject', 'forms', 'We hebben je aanvraag voor formulier :name: ontvangen!', 'text', [
            'name' => $this->formInput->form->name,
        ]);

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($this->templateSubject($fallbackSubject, $context));
        }

        return $this->view(config('dashed-core.site_theme', 'dashed') . '.emails.custom-confirm-form-submit')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject($fallbackSubject)
            ->with(['formInput' => $this->formInput]);
    }
}
