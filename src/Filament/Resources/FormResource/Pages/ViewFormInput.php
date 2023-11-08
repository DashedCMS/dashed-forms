<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedForms\Filament\Resources\FormResource;

class ViewFormInput extends Page
{
    protected static string $resource = FormResource::class;

    protected static string $view = 'dashed-forms::forms.pages.view-form-input';

    public $record;

    public function mount($record, FormInput $formInput): void
    {
        $this->record = $formInput;
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $lastBreadcrumb = $breadcrumbs[0];
        array_pop($breadcrumbs);
        $breadcrumbs[route('filament.dashed.resources.forms.viewInputs', [$this->record->form->id])] = "Aanvragen voor {$this->record->form->name}";
        $breadcrumbs[] = $lastBreadcrumb;

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        $actions = [
            Action::make('mark_as_not_viewed')
                ->button()
                ->visible($this->record->viewed)
                ->label('Markeer als niet bekeken')
                ->action('markAsNotViewed'),
            Action::make('mark_as_viewed')
                ->button()
                ->visible(!$this->record->viewed)
                ->label('Markeer als bekeken')
                ->action('markAsViewed'),
            Action::make('delete')
                ->button()
                ->requiresConfirmation()
                ->color('danger')
                ->label('Verwijderen')
                ->action('delete'),
        ];

        return $actions;
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

    public function delete()
    {
        $this->record->delete();

        return redirect()->route('filament.dashed.resources.forms.viewInputs', [$this->record->form->id]);
    }

    public function getTitle(): string
    {
        return "Aanvraag #{$this->record->id} voor {$this->record->form->name}";
    }
}
