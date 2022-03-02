<?php

namespace Qubiqx\QcommerceForms\Filament\Resources\FormResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Pages\Actions\ButtonAction;
use Qubiqx\QcommerceForms\Models\FormInput;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource;

class ViewFormInput extends Page
{
    protected static string $resource = FormResource::class;

    protected static string $view = 'qcommerce-core::forms.pages.view-form-input';

    public $record;

    public function mount($record, FormInput $formInput): void
    {
        $this->record = $formInput;
    }

    protected function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $lastBreadcrumb = $breadcrumbs[0];
        array_pop($breadcrumbs);
        $breadcrumbs[route('filament.resources.forms.view', [$this->record->form->id])] = "Aanvragen voor {$this->record->form->name}";
        $breadcrumbs[] = $lastBreadcrumb;

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        if ($this->record->viewed == 1) {
            return [
                ButtonAction::make('mark_as_not_viewed')
                    ->label('Markeer als niet bekeken')
                    ->action('markAsNotViewed'),
            ];
        } else {
            return [
                ButtonAction::make('mark_as_viewed')
                    ->label('Markeer als bekeken')
                    ->action('markAsViewed'),
            ];
        }
    }

    public function markAsNotViewed(): void
    {
        $this->record->viewed = 0;
        $this->record->save();
    }

    public function markAsViewed(): void
    {
        $this->record->viewed = 1;
        $this->record->save();
    }

    protected function getTitle(): string
    {
        return "Aanvraag #{$this->record->id} voor {$this->record->form->name}";
    }
}
