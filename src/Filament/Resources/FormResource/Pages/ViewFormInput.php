<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Page;
use Dashed\DashedCore\Classes\Sites;
use Filament\Schemas\Components\Flex;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedForms\Models\FormInput;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Contracts\HasInfolists;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class ViewFormInput extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static string $resource = FormResource::class;

    protected string $view = 'dashed-forms::forms.pages.view-form-input';

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
        return [
            Action::make('toggleViewed')
                ->button()
                ->label($this->record->viewed ? 'Markeer als niet bekeken' : 'Markeer als bekeken')
                ->color($this->record->viewed ? 'warning' : 'success')
                ->action(function () {
                    if ($this->record->viewed) {
                        $this->markAsNotViewed();
                    } else {
                        $this->markAsViewed();
                    }

                    return redirect()->route('filament.dashed.resources.forms.viewInput', [$this->record->form->id, $this->record->id]);
                }),
            Action::make('delete')
                ->button()
                ->requiresConfirmation()
                ->color('danger')
                ->label('Verwijderen')
                ->action('delete'),
        ];
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

    public function infolist(Schema $schema): Schema
    {
        $inputFields = [];

        if ($this->record->content) {
            foreach ($this->record->content as $key => $value) {
                $label = Str::of($key)->replace('_', ' ')->title();
                $name = 'content_' . Str::slug((string)$key, '_');

                $inputFields[] = TextEntry::make($name)
                    ->label($label)
                    ->state(is_array($value) ? json_encode($value) : $value);
            }
        } else {
            foreach ($this->record->formFields as $field) {
                $id = (string)($field->formField->id ?? Str::random(8));
                $name = 'field_' . $id;

                if ($field->isImage()) {
                    if ($field->formField->type === 'select-image') {
                        $inputFields[] = ImageEntry::make($name)
                            ->label($field->formField->name)
                            ->helperText(collect($field->formField->images)->where('image', $field->value)->first()['name'] ?? null)
                            ->state($field->value);
                    } else {
                        if (str($field->value)->contains(['.jpg', '.jpeg', '.png', '.gif', '.svg'])) {
                            $inputFields[] = ImageEntry::make($name)
                                ->label($field->formField->name)
                                ->url(Storage::disk('dashed')->url($field->value))
                                ->openUrlInNewTab()
                                ->helperText('Klik de afbeelding om te openen')
                                ->state($field->value);
                        } else {
                            $inputFields[] = TextEntry::make($name . '_download')
                                ->state($field->formField->name)
                                ->label('Download bestand')
                                ->url(Storage::disk('dashed')->url($field->value))
                                ->openUrlInNewTab();
                        }
                    }
                } else {
                    $inputFields[] = TextEntry::make($name)
                        ->label($field->formField->name)
                        ->state($field->value)
                        ->prose();
                }
            }
        }

        $inputFields[] = TextEntry::make('viewed_status_badge')
            ->label('Bekeken')
            ->badge()
            ->formatStateUsing(fn (): string => $this->record->viewed ? 'Ja' : 'Nee')
            ->color(fn (): string => $this->record->viewed ? 'success' : 'danger');

        return $schema
            ->record($this->record)
            ->schema([
                Flex::make([
                    Section::make('Ingevoerde informatie')
                        ->schema($inputFields)
                        ->columnSpanFull()
                        ->grow(),
                    Section::make('Overige informatie')
                        ->schema([
                            TextEntry::make('ip')
                                ->label('IP')
                                ->default('Onbekend'),
                            TextEntry::make('user_agent')
                                ->label('User agent')
                                ->default('Onbekend'),
                            TextEntry::make('from_url')
                                ->label('Ingevoerd vanaf')
                                ->url(fn () => $this->record->from_url)
                                ->openUrlInNewTab()
                                ->default('Onbekend'),
                            TextEntry::make('created_at')
                                ->label('Ingevoerd op')
                                ->default('Onbekend'),
                            TextEntry::make('site_id')
                                ->label('Site ID')
                                ->visible(count(Sites::getSites()) > 1)
                                ->default('Onbekend'),
                            TextEntry::make('locale')
                                ->label('Taal')
                                ->visible(count(Locales::getLocales()) > 1)
                                ->default('Onbekend'),
                        ])
                        ->columnSpanFull(),
                ])->from('md'),
            ]);
    }

    public function getTitle(): string
    {
        return "Aanvraag #{$this->record->id} voor {$this->record->form->name}";
    }
}
