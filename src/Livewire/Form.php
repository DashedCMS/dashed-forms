<?php

namespace Qubiqx\QcommerceForms\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceForms\Mail\AdminCustomFormSubmitConfirmationMail;
use Qubiqx\QcommerceForms\Mail\CustomFormSubmitConfirmationMail;
use Qubiqx\QcommerceForms\Models\FormField;
use Qubiqx\QcommerceForms\Models\FormInput;

class Form extends Component
{
    use WithFileUploads;

    public \Qubiqx\QcommerceForms\Models\Form $form;
    public array $values = [];
    public array $blockData = [];
    public bool $formSent = false;
    public ?string $myName = '';
    public bool $singleColumn = false;
    public ?string $buttonTitle = '';

    public function mount(\Qubiqx\QcommerceForms\Models\Form $formId, array $blockData = [], bool $singleColumn = false, ?string $buttonTitle = '')
    {
        $this->singleColumn = $singleColumn;
        $this->form = $formId;
        $this->blockData = $blockData;
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
                'radio' => $field->required ? $this->values[$field->fieldName] = $field->options[0]['name'] : null,
                'select' => $this->values[$field->fieldName] = $field->options[0]['name'],
                'select-image' => $this->values[$field->fieldName] = $field->images[0]['image'],
                'input' => $this->values[$field->fieldName] = '',
                'textarea' => $this->values[$field->fieldName] = '',
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

    protected function rules()
    {
        return collect($this->formFields)
            ->flatMap(fn (FormField $field) => ['values.' . $field->fieldName => $this->mapRules($field)])
            ->toArray();
    }

    public function submit()
    {
        $this->validate();

        if ($this->myName) {
            $this->addError('values.' . $this->form->fields()->where('type', '!=', 'info')->first()->fieldName, 'Je bent een bot!');

            return Notification::make()
                ->danger()
                ->body('Je bent een bot!')
                ->send();
        }

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
            if ($field->type == 'checkbox') {
                $value = implode(', ', $value);
            }

            if ($value) {
                $formInput->formFields()->create([
                    'value' => $value,
                    'form_field_id' => $field->id,
                ]);

                if ($formInput->form->emailConfirmationFormField && $field->id == $formInput->form->emailConfirmationFormField->id) {
                    $sendToFieldValue = $value;
                }
            }
        }

        if ($sendToFieldValue ?? false) {
            try {
                Mail::to($sendToFieldValue)->send(new CustomFormSubmitConfirmationMail($formInput));
            } catch (\Exception $e) {
            }
        }

        try {
            $notificationFormInputsEmails = Customsetting::get('notification_form_inputs_emails', Sites::getActive(), '[]');
            if ($notificationFormInputsEmails) {
                foreach (json_decode($notificationFormInputsEmails) as $notificationFormInputsEmail) {
                    Mail::to($notificationFormInputsEmail)->send(new AdminCustomFormSubmitConfirmationMail($formInput));
                }
            }
        } catch (\Exception $e) {
        }

        $this->resetForm();
        $this->formSent = true;
        Notification::make()
            ->success()
            ->body('Je bericht is verzonden!')
            ->send();
    }

    public function updated($name, $value)
    {
        if ($value instanceof TemporaryUploadedFile) {
            $path = $value->storeAs('qcommerce', "forms/form-{$this->form->name}-" . time() . '.' . $value->getClientOriginalExtension());
            $this->values[str($name)->explode('.')->last()] = $path;
        }
    }

    public function setValueForField(string $field, string $value)
    {
        $this->values[$field] = $value;
    }

    public function render()
    {
        return view('qcommerce.forms.form');
    }
}
