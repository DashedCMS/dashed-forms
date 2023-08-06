<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedForms\Classes\Forms;
use Dashed\DashedForms\Filament\Resources\FormResource;

class ListForm extends ListRecords
{
    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return array_merge(parent::getActions(), [
            Action::make('createContactForm')
                ->label('Contact formulier aanmaken')
            ->action(function () {
                Forms::createPresetForms('contact');
                $this->notify('success', 'Contact formulier aangemaakt');
            }),
        ]);
    }
}
