<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Dashed\DashedForms\Classes\Forms;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListForm extends ListRecords
{
    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('createContactForm')
                ->label('Contact formulier aanmaken')
                ->action(function () {
                    Forms::createPresetForms('contact');
                    Notification::make()
                        ->title('Contact formulier aangemaakt')
                        ->success()
                        ->send();
                }),
        ];
    }
}
