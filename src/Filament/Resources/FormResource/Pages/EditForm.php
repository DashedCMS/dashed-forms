<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedForms\Filament\Resources\FormResource;

class EditForm extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
            Action::make('duplicate')
                ->action('duplicate')
                ->button()
                ->label('Dupliceer'),
            DeleteAction::make(),
        ];
    }

    public function duplicate()
    {
        $newRecord = $this->record->replicate();
        $newRecord->save();

        foreach ($this->record->fields as $field) {
            $newField = $field->replicate();
            $newField->form_id = $newRecord->id;
            $newField->save();
        }

        return redirect(route('filament.dashed.resources.forms.edit', [$newRecord]));
    }
}
