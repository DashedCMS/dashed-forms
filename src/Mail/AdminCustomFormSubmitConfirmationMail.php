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
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class AdminCustomFormSubmitConfirmationMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public FormInput $formInput;

    public ?string $replyToEmail;

    public function __construct(FormInput $formInput, ?string $replyToEmail = '')
    {
        $this->formInput = $formInput;
        $this->replyToEmail = $replyToEmail;
    }

    public static function emailTemplateName(): string
    {
        return 'Formulier bevestiging (custom, beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder als een custom formulier is ingediend.';
    }

    public static function availableVariables(): array
    {
        return ['formName', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Het formulier :formName: is ingevuld!';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Nieuw formulier ingediend', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Het formulier <strong>:formName:</strong> is ingevuld. Hieronder de ingevoerde gegevens:</p>']],
            ['type' => 'form-submission', 'data' => ['title' => 'Ingevoerde gegevens']],
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

        $fallbackSubject = Translation::get('admin-form-confirmation-' . Str::slug($this->formInput->form->name) . '-email-subject', 'forms', 'Het formulier :name: is ingevuld!', 'text', [
            'name' => $this->formInput->form->name,
        ]);

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($this->templateSubject($fallbackSubject, $context));
        } else {
            $mail = $this->view(config('dashed-core.site_theme', 'dashed') . '.emails.admin-custom-confirm-form-submit')
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject($fallbackSubject)
                ->with(['formInput' => $this->formInput]);
        }

        if ($this->replyToEmail) {
            $mail->replyTo($this->replyToEmail);
        }

        return $mail;
    }

    public function telegramSummary(): TelegramSummary
    {
        $fields = [];

        foreach ($this->formInput->formFields ?? [] as $inputField) {
            $label = $inputField->formField?->name;
            $label = is_array($label) ? ($label[app()->getLocale()] ?? reset($label)) : $label;
            $value = $inputField->value;
            if ($value === null || $value === '') {
                continue;
            }
            $fields[(string) ($label ?: '—')] = (string) $value;
        }

        if (empty($fields)) {
            foreach ($this->formInput->content ?? [] as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value));
                }
                if (! is_scalar($value) || $value === '') {
                    continue;
                }
                $fields[(string) $key] = (string) $value;
            }
        }

        return new TelegramSummary(
            title: 'Custom form inzending',
            fields: $fields ?: ['Status' => 'Inzending ontvangen'],
            emoji: '📝',
        );
    }
}
