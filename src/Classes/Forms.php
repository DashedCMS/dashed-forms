<?php

namespace Qubiqx\QcommerceForms\Classes;

use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\App;
use Qubiqx\QcommerceForms\Models\Form;

class Forms
{
    public static function getPostUrl()
    {
        return route('qcommerce.frontend.forms.store');
    }

    public static function availableInputTypes(): array
    {
        $validTypes = [
            'info' => 'Informatie',
            'image' => 'Afbeelding',
            'input' => 'Tekst',
            'textarea' => 'Tekstvak',
            'checkbox' => 'Checkbox',
            'radio' => 'Radio',
            'select' => 'Select',
            'select-image' => 'Selecteer afbeelding',
            'file' => 'Bestand',
        ];

        foreach ($validTypes as $key => $validType) {
            if (! view()->exists('components.form-components.' . $key)) {
                unset($validTypes[$key]);
            }
        }

        return $validTypes;
    }

    public static function availableInputTypesForInput(): array
    {
        return [
            'text' => 'Tekst',
            'email' => 'Email',
            'number' => 'Nummer',
            'date' => 'Datum',
            'dateTime' => 'Datum en tijd',
            'file' => 'Bestand',
        ];
    }

    public static function formSelecter(string $name = 'form', bool $required = true): Select
    {
        return
            Select::make($name)
                ->label('Formulier')
                ->options(Form::all()->pluck('name', 'id'))
                ->required($required);
    }

    public static function createPresetForms(string $presetForm = 'contact')
    {
        if ($presetForm == 'contact') {
            $form = Form::create([
                'name' => 'Contact formulier',
            ]);

            $form->fields()->create([
                'name' => [
                    App::getLocale() => 'Naam',
                ],
                'type' => 'input',
                'input_type' => 'text',
                'required' => 1,
                'sort' => 1,
                'helper_text' => [],
            ]);

            $emailField = $form->fields()->create([
                'name' => [
                    App::getLocale() => 'E-mailadres',
                ],
                'type' => 'input',
                'input_type' => 'email',
                'required' => 1,
                'sort' => 2,
                'helper_text' => [],
            ]);

            $form->fields()->create([
                'name' => [
                    App::getLocale() => 'Bedrijfsnaam',
                ],
                'type' => 'input',
                'input_type' => 'text',
                'required' => 0,
                'sort' => 3,
                'helper_text' => [],
            ]);

            $form->fields()->create([
                'name' => [
                    App::getLocale() => 'Telefoonnummer',
                ],
                'type' => 'input',
                'input_type' => 'text',
                'required' => 0,
                'sort' => 4,
                'helper_text' => [],
            ]);

            $form->fields()->create([
                'name' => [
                    App::getLocale() => 'Bericht',
                ],
                'type' => 'textarea',
                'required' => 1,
                'sort' => 5,
                'placeholder' => [
                    App::getLocale() => 'Waar kunnen we je mee helpen?',
                ],
                'helper_text' => [],
            ]);

            $form->email_confirmation_form_field_id = $emailField->id;
            $form->save();
        }
    }
}
