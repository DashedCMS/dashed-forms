<?php

namespace Dashed\DashedForms\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedForms\Models\FormField;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedForms\Enums\MailingProviders;
use Dashed\DashedForms\Mail\CustomFormSubmitConfirmationMail;
use Dashed\DashedForms\Mail\AdminCustomFormSubmitConfirmationMail;
use Dashed\DashedTranslations\Models\Translation;
use Filament\Notifications\Notification;
use DutchCodingCompany\LivewireRecaptcha\ValidatesRecaptcha;

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

    protected $listeners = [
        'setValue',
    ];

    public function mount(\Dashed\DashedForms\Models\Form $formId, array $blockData = [], array $inputData = [], bool $singleColumn = false, ?string $buttonTitle = '')
    {
        if (Customsetting::get('google_recaptcha_site_key')) {
            config([
                'services.google.recaptcha.site_key'   => Customsetting::get('google_recaptcha_site_key'),
                'services.google.recaptcha.secret_key' => Customsetting::get('google_recaptcha_secret_key'),
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
                'input' => $this->values[$field->fieldName] = request()->get(str($field->name)->slug(), $this->inputData[(string)str($field->name)->slug()] ?? ''),
                'textarea' => $this->values[$field->fieldName] = request()->get(str($field->name)->slug(), $this->inputData[(string)str($field->name)->slug()] ?? ''),
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
                $rules[] = 'regex:' . $field->regex;
            }
        }

        if ($field->type === 'textarea') {
            $rules[] = 'max:5000';
            $rules[] = 'string';
        }

        return $rules;
    }

    protected function validationAttributes()
    {
        return collect($this->formFields)
            ->flatMap(fn (FormField $field) => ['values.' . $field->fieldName => strtolower($field->name)])
            ->toArray();
    }

    public function rules()
    {
        return collect($this->formFields)
            ->flatMap(fn (FormField $field) => ['values.' . $field->fieldName => $this->mapRules($field)])
            ->toArray();
    }

    public function setValue($field, $value)
    {
        $this->values[$field] = $value;
    }

    #[ValidatesRecaptcha]
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

                $formValues[$field->name] = $field->type == 'file'
                    ? Storage::disk('dashed')->url($value)
                    : $value;
            }
        }

        if ($sendToFieldValue ?? false) {
            try {
                Mail::to($sendToFieldValue)->send(new CustomFormSubmitConfirmationMail($formInput));
            } catch (\Exception $e) {
            }
        }

        try {
            $notificationFormInputsEmails = $this->form->notification_form_inputs_emails ?: Customsetting::get('notification_form_inputs_emails', Sites::getActive(), []);
            if (count($notificationFormInputsEmails)) {
                foreach ($notificationFormInputsEmails as $notificationFormInputsEmail) {
                    Mail::to($notificationFormInputsEmail)->send(new AdminCustomFormSubmitConfirmationMail($formInput, $sendToFieldValue ?? null));
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
            ->body(Translation::get('your-form-' . str($this->form->name)->slug() . '-has-been-sent', 'form', 'Je bericht is verzonden!'))
            ->send();

        $redirectUrl = $this->form->redirect_after_form ? linkHelper()->getUrl($this->form->redirect_after_form) : '';

        $this->dispatch('formSubmitted', [
            'formId'      => $this->form->id,
            'redirectUrl' => $redirectUrl,
            'data'        => $formValues,
            'formName'    => $this->form->name,
        ]);

        if ($redirectUrl && Customsetting::get('form_redirect_server_side', null, true)) {
            return redirect($redirectUrl);
        }
    }

    public function updated($name, $value)
    {
        if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $path = $value->storeAs(
                'dashed',
                "forms/form-{$this->form->name}-" . time() . '.' . $value->getClientOriginalExtension(),
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
        if (view()->exists('dashed.forms.' . str($this->form->name)->slug() . '-form')) {
            return view(config('dashed-core.site_theme') . '.forms.' . str($this->form->name)->slug() . '-form');
        }

        return view(config('dashed-core.site_theme') . '.forms.form');
    }
}
