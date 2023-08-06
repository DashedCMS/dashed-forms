<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Pages\Actions;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedForms\Filament\Resources\FormResource;

class EditForm extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return [
            Action::make('duplicate')
                ->action('duplicate')
                ->button()
                ->label('Dupliceer'),
            $this->getActiveFormLocaleSelectAction(),
            Actions\DeleteAction::make(),
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

        return redirect(route('filament.resources.forms.edit', [$newRecord]));
    }
}
