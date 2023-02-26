<?php

namespace Qubiqx\QcommerceForms\Filament\Resources\FormResource\Pages;

use App\Filament\Resources\QuotationFormResource;
use App\Models\Market;
use Filament\Pages\Actions;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Locales;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource;

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

        foreach ($this->record->formFields as $field) {
            $newField = $field->replicate();
            $newField->form_id = $newRecord->id;
            $newField->save();
        }

        return redirect(route('filament.resources.forms.edit', [$newRecord]));
    }
}
