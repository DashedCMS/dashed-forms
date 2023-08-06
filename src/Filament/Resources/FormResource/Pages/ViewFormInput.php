<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Dashed\DashedForms\Filament\Resources\FormResource;
use Dashed\DashedForms\Models\FormInput;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\Page;

class ViewFormInput extends Page
{
    protected static string $resource = FormResource::class;

    protected static string $view = 'dashed-forms::forms.pages.view-form-input';

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
        $breadcrumbs[route('filament.resources.forms.viewInputs', [$this->record->form->id])] = "Aanvragen voor {$this->record->form->name}";
        $breadcrumbs[] = $lastBreadcrumb;

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        $actions = [];

        if ($this->record->viewed == 1) {
            $actions[] = Action::make('mark_as_not_viewed')
                ->button()
                ->label('Markeer als niet bekeken')
                ->action('markAsNotViewed');
        } else {
            $actions[] = Action::make('mark_as_viewed')
                ->button()
                ->label('Markeer als bekeken')
                ->action('markAsViewed');
        }

        $actions[] = Action::make('delete')
            ->button()
            ->color('danger')
            ->label('Verwijderen')
            ->action('delete');

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

        return redirect()->route('filament.resources.forms.viewInputs', [$this->record->form->id]);
    }

    protected function getTitle(): string
    {
        return "Aanvraag #{$this->record->id} voor {$this->record->form->name}";
    }
}
