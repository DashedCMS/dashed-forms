<?php

namespace Dashed\DashedForms\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\App;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedForms\Models\FormField;
use Dashed\DashedForms\Models\FormInput;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Classes\EmailCapture;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedForms\Events\FormSubmitted;
use Dashed\DashedForms\Enums\MailingProviders;
use Dashed\DashedForms\Jobs\SyncFormInputApisJob;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedForms\Validations\ValidatesMcaptcha;
use Dashed\DashedForms\Validations\ValidatesRecaptcha;
use Dashed\DashedForms\Mail\CustomFormSubmitConfirmationMail;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Dashed\DashedForms\Mail\AdminCustomFormSubmitConfirmationMail;

class Form extends Component
{
    use WithFileUploads;

    public \Dashed\DashedForms\Models\Form $form;

    public array $values = [];

    public array $blockData = [];

    public array $inputData = [];

    public bool $formSent = false;

    public bool $singleColumn = false;

    public ?string $buttonTitle = '';

    public string $gRecaptchaResponse = ''; // wordt door de package gebruikt

    public string $mcaptchaToken = ''; // gevuld door mCaptcha vanilla-glue script

    protected $listeners = [
        'setValue',
    ];

    public function mount(\Dashed\DashedForms\Models\Form $formId, array $blockData = [], array $inputData = [], bool $singleColumn = false, ?string $buttonTitle = '')
    {
        if (Customsetting::get('google_recaptcha_site_key')) {
            config([
                'services.google.recaptcha.site_key' => Customsetting::get('google_recaptcha_site_key'),
                'services.google.recaptcha.secret_key' => Customsetting::get('google_recaptcha_secret_key'),
            ]);
        }

        if (Customsetting::get('mcaptcha_site_key')) {
            config([
                'services.mcaptcha.instance_url' => Customsetting::get('mcaptcha_instance_url'),
                'services.mcaptcha.site_key' => Customsetting::get('mcaptcha_site_key'),
                'services.mcaptcha.secret' => Customsetting::get('mcaptcha_secret'),
            ]);
        }

        $this->singleColumn = $singleColumn;
        $this->form = $formId;
        $this->blockData = $blockData;
        $this->inputData = $inputData;
        $this->buttonTitle = $buttonTitle;
        $this->resetForm();
    }

    public function getFormFieldsProperty()
    {
        return $this->form->fields;
    }

    public function resetForm()
    {
        foreach ($this->formFields as $field) {
            match ($field->type) {
                'radio' => $field->required ? $this->values[$field->fieldName] = ($field->options[0]['name'] ?? []) : [],
                'select' => $this->values[$field->fieldName] = ($field->options[0]['name'] ?? null),
                'select-image' => $this->values[$field->fieldName] = ($field->options[0]['image'] ?? null),
                'input' => $this->values[$field->fieldName] = request()->get(str($field->name)->slug(), $this->inputData[(string) str($field->name)->slug()] ?? ''),
                'textarea' => $this->values[$field->fieldName] = request()->get(str($field->name)->slug(), $this->inputData[(string) str($field->name)->slug()] ?? ''),
                'file' => $this->values[$field->fieldName] = '',
                default => null,
            };
        }
    }

    protected function mapRules(FormField $field): array
    {
        $rules = [
            'nullable',
        ];

        if ($field->required) {
            $rules[] = 'required';
        }

        if ($field->type === 'input') {
            $rules[] = 'max:255';
            $rules[] = 'string';

            if ($field->regex) {
                $rules[] = 'regex:'.$field->regex;
            }
        }

        if ($field->type === 'textarea') {
            $rules[] = 'max:5000';
            $rules[] = 'string';
        }

        if ($field->type === 'file') {
            $rules[] = 'file';
            $rules[] = 'max:10240';
            $rules[] = 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx';
        }

        return $rules;
    }

    protected function validationAttributes()
    {
        return collect($this->formFields)
            ->flatMap(fn (FormField $field) => ['values.'.$field->fieldName => strtolower($field->name)])
            ->toArray();
    }

    public function rules()
    {
        return collect($this->formFields)
            ->flatMap(fn (FormField $field) => ['values.'.$field->fieldName => $this->mapRules($field)])
            ->toArray();
    }

    public function setValue($field, $value)
    {
        $this->values[$field] = $value;
    }

    #[ValidatesRecaptcha]
    #[ValidatesMcaptcha]
    public function submit()
    {
        $this->validate();

        // 2. Rest van je oorspronkelijke flow
        $formValues = [];

        $formInput = new FormInput();
        $formInput->form_id = $this->form->id;
        $formInput->ip = request()->ip();
        $formInput->user_agent = request()->userAgent();
        $formInput->from_url = url()->previous();
        $formInput->site_id = Sites::getActive();
        $formInput->locale = App::getLocale();
        $formInput->save();

        foreach ($this->values as $fieldName => $value) {
            $field = FormField::find(str($fieldName)->explode('-')->last());
            if (! $field) {
                continue;
            }

            if ($field->type == 'checkbox' && is_array($value)) {
                $value = array_keys($value);
                $value = str(implode(', ', $value))->headline();
            }

            if ($value !== null && $value !== '') {
                $formInput->formFields()->create([
                    'value' => $value,
                    'form_field_id' => $field->id,
                ]);

                if ($formInput->form->emailConfirmationFormField && $field->id == $formInput->form->emailConfirmationFormField->id) {
                    $sendToFieldValue = $value;
                }

                // Elke email-veldwaarde meteen in de globale captura
                // stoppen — dit dekt alle losse formulieren (contact,
                // nieuwsbrief, custom) zonder per form expliciete hooks.
                // dashed-forms gebruikt `type=input` met `input_type=email`
                // voor e-mail-velden; daarnaast valideren we de waarde zelf
                // zodat ook generieke text-velden met geldige adressen
                // worden meegepakt.
                if (is_string($value) && (
                    (($field->type ?? null) === 'input' && ($field->input_type ?? null) === 'email')
                    || filter_var(trim($value), FILTER_VALIDATE_EMAIL)
                )) {
                    EmailCapture::capture($value, 'form:'.($formInput->form->name ?? 'unknown'));
                }

                $formValues[$field->name] = $field->type == 'file'
                    ? Storage::disk('dashed')->url($value)
                    : $value;
            }
        }

        // FormSubmitted event lets downstream marketing listeners (newsletter
        // enrolment, lead-flow signup, etc.) react without coupling form-input
        // persistence to specific consumers.
        event(new FormSubmitted(
            form_id: (int) $formInput->form_id,
            form_input_id: (int) $formInput->id,
            email: $sendToFieldValue ?? null,
            locale: $formInput->locale ?: app()->getLocale(),
            site_id: $formInput->site_id,
        ));

        if ($formInput->should_send_api && (int) $formInput->api_send !== 1) {
            SyncFormInputApisJob::dispatch($formInput->id);
        }

        if ($sendToFieldValue ?? false) {
            try {
                Mail::to($sendToFieldValue)->send(new CustomFormSubmitConfirmationMail($formInput));
            } catch (\Exception $e) {
            }
        }

        try {
            $adminMailable = new AdminCustomFormSubmitConfirmationMail($formInput, $sendToFieldValue ?? null);

            // Telegram één keer versturen, los van het aantal email-recipients,
            // zodat het channel werkt zelfs als notification_form_inputs_emails
            // leeg is, en niet N keer afgaat bij meerdere mail-recipients.
            AdminNotifier::send($adminMailable, null, ['telegram']);

            $notificationFormInputsEmails = $this->form->notification_form_inputs_emails ?: Customsetting::get('notification_form_inputs_emails', Sites::getActive(), []);
            if (count($notificationFormInputsEmails)) {
                foreach ($notificationFormInputsEmails as $notificationFormInputsEmail) {
                    AdminNotifier::send($adminMailable, $notificationFormInputsEmail, ['mail']);
                }
            }
        } catch (\Exception $e) {
        }

        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $provider->createContactFromFormInput($formInput);
            }
        }

        $this->resetForm();
        $this->formSent = true;

        Notification::make()
            ->success()
            ->body(Translation::get('your-form-'.str($this->form->name)->slug().'-has-been-sent', 'form', 'Je bericht is verzonden!'))
            ->send();

        $redirectUrl = $this->form->redirect_after_form ? linkHelper()->getUrl($this->form->redirect_after_form) : '';

        $this->dispatch('formSubmitted', [
            'formId' => $this->form->id,
            'redirectUrl' => $redirectUrl,
            'data' => $formValues,
            'formName' => $this->form->name,
        ]);

        if ($redirectUrl && Customsetting::get('form_redirect_server_side', null, true)) {
            return redirect($redirectUrl);
        }
    }

    public function updated($name, $value)
    {
        if ($value instanceof TemporaryUploadedFile) {
            // Rate limiting tegen ongeauthenticeerde upload-/opslag-misbruik (DoS).
            $uploadKey = 'form-upload:' . request()->ip();
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($uploadKey, 20)) {
                $this->values[str($name)->explode('.')->last()] = null;
                $this->addError($name, 'Te veel uploads, probeer het later opnieuw.');

                return;
            }
            \Illuminate\Support\Facades\RateLimiter::hit($uploadKey, 60);

            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
            // Leid de extensie af van de inhoud (guessExtension), niet van de client-naam.
            $extension = strtolower((string) ($value->guessExtension() ?: $value->getClientOriginalExtension()));

            // Het bestand wordt hier al permanent opgeslagen (vóór submit-validatie), dus
            // controleer extensie en grootte meteen en weiger ongeldige uploads.
            if (! in_array($extension, $allowedExtensions, true) || $value->getSize() > 10 * 1024 * 1024) {
                $this->values[str($name)->explode('.')->last()] = null;
                $this->addError($name, 'Ongeldig of te groot bestand.');

                return;
            }

            $path = $value->storeAs(
                'dashed',
                'forms/form-'.\Illuminate\Support\Str::slug($this->form->name).'-'.\Illuminate\Support\Str::random(40).'.'.$extension,
                'dashed'
            );

            $this->values[str($name)->explode('.')->last()] = $path;
        }
    }

    public function setValueForField(string $field, string $value)
    {
        $this->values[$field] = $value;
    }

    public function render()
    {
        if (view()->exists('dashed.forms.'.str($this->form->name)->slug().'-form')) {
            return view(config('dashed-core.site_theme', 'dashed').'.forms.'.str($this->form->name)->slug().'-form');
        }

        return view(config('dashed-core.site_theme', 'dashed').'.forms.form');
    }
}
