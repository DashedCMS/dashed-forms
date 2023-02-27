<?php

namespace Qubiqx\QcommerceForms\Filament\Resources\FormResource\Pages;

use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Qubiqx\QcommerceForms\Classes\Forms;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource;

class ListForm extends ListRecords
{
    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return array_merge(parent::getActions(), [
            Action::make('createContactForm')
                ->label('Contact formulier aanmaken')
            ->action(function(){
                Forms::createPresetForms('contact');
                $this->notify('success', 'Contact formulier aangemaakt');
            }),
        ]);
    }
}
