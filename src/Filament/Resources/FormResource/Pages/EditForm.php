<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedForms\Models\FormField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedForms\Filament\Resources\FormResource;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditForm extends EditRecord
{
    use Translatable;
    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
            Action::make('translate')
                ->icon('heroicon-m-language')
                ->label('Vertaal')
                ->visible(AutomatedTranslation::automatedTranslationsEnabled())
                ->schema([
                    Select::make('to_locales')
                        ->options(Locales::getLocalesArray())
                        ->preload()
                        ->searchable()
                        ->default(fn ($livewire) => collect(Locales::getLocalesArrayWithoutCurrent($livewire->activeLocale))->keys()->toArray())
                        ->required()
                        ->label('Naar talen')
                        ->multiple(),
                ])
                ->action(function (array $data) {
                    foreach ($this->record->fields as $field) {
                        AutomatedTranslation::translateModel($field, $this->activeLocale, $data['to_locales']);
                    }

                    Notification::make()
                        ->title('Item wordt vertaald, dit kan even duren. Sla de pagina niet op tot de vertalingen klaar zijn.')
                        ->warning()
                        ->send();

                    return redirect()->to(request()->header('Referer'));
                }),
            Action::make('duplicate')
                ->action('duplicate')
                ->icon('heroicon-m-document-duplicate')
                ->button()
                ->label('Dupliceer'),
            DeleteAction::make()
                ->icon('heroicon-m-trash'),
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['mustHaveSomethingDefined']);
        foreach ($data as $key => $value) {
            if (str($key)->contains('redirect_after_form')) {
                $key = str($key)->replace('redirect_after_form_', '');
                $data['redirect_after_form']['url_' . $key] = $data['redirect_after_form_' . $key] ?? '';
                unset($data['redirect_after_form_' . $key]);
            }
        }

        if (! FormField::find($data['email_confirmation_form_field_id'] ?? 0)) {
            $data['email_confirmation_form_field_id'] = null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($data['redirect_after_form'] ?? [] as $key => $value) {
            $data['redirect_after_form_' . str($key)->replace('url_', '')] = $value;
        }

        unset($data['redirect_after_form']);

        return parent::mutateFormDataBeforeFill($data);
    }

    public function updatingActiveLocale($newVal): void
    {
        $this->oldActiveLocale = $this->activeLocale;
        $this->save();

        foreach ($this->data['fields'] ?? [] as $key => $fieldArray) {
            $relation = $this->getRecord()->fields()->find($fieldArray['id'] ?? 0);
            if ($relation) {
                foreach ($relation->translatable as $attribute) {
                    $this->data['fields'][$key][$attribute] = $relation->getTranslation($attribute, $newVal);
                }
            }
        }
    }
}
