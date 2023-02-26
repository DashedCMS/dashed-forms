<?php

namespace Qubiqx\QcommerceForms\Classes;

use Filament\Forms\Components\Select;
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
            'input' => 'Tekst',
            'textarea' => 'Tekstvak',
            'checkbox' => 'Checkbox',
            'radio' => 'Radio',
            'select' => 'Select',
            'select-image' => 'Selecteer afbeelding',
            'file' => 'Bestand',
        ];

        foreach ($validTypes as $key => $validType) {
            if (!view()->exists('components.form-components.' . $key)) {
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

    public static function formSelecter(): Select
    {
        return
            Select::make('form')
                ->label('Formulier')
                ->options(Form::all()->pluck('name', 'id'))
                ->required();
    }
}
