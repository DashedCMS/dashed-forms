<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Page;
use Dashed\DashedCore\Classes\Sites;
use Filament\Schemas\Components\Flex;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Dashed\DashedForms\Models\FormInput;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Contracts\HasInfolists;
use Dashed\DashedForms\Services\FormReplyService;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class ViewFormInput extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static string $resource = FormResource::class;

    protected string $view = 'dashed-forms::forms.pages.view-form-input';

    public $record;

    public ?string $draft = null;

    public function mount($record, FormInput $formInput): void
    {
        $this->record = $formInput;
    }

    private function replyService(): FormReplyService
    {
        return app(FormReplyService::class);
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
            Action::make('aiDraft')
                ->button()
                ->color('primary')
                ->icon('heroicon-o-sparkles')
                ->label('AI-concept opstellen')
                ->visible(fn (): bool => $this->replyService()->aiAvailable() && $this->replyService()->recipientEmail($this->record) !== null)
                ->modalSubmitActionLabel('Genereer')
                ->form([
                    Textarea::make('instructions')
                        ->label('Eigen input voor de AI (optioneel)')
                        ->placeholder('Bijv. "bied excuses aan en zeg dat we morgen leveren" of laat leeg voor een standaard antwoord.')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->draft = $this->replyService()->generateDraft($this->record, $data['instructions'] ?? null);
                    } catch (\Throwable $e) {
                        Notification::make()->title('Kon geen concept maken')->body($e->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Concept klaar — open "Antwoord versturen" om te controleren en te verzenden.')->success()->send();
                }),
            Action::make('sendReply')
                ->button()
                ->color('success')
                ->icon('heroicon-o-paper-airplane')
                ->label('Antwoord versturen')
                ->visible(fn (): bool => $this->replyService()->recipientEmail($this->record) !== null)
                ->modalSubmitActionLabel('Versturen')
                ->form([
                    Textarea::make('message')
                        ->label('Antwoord')
                        ->helperText(fn (): string => 'Wordt per e-mail verstuurd naar ' . ($this->replyService()->recipientEmail($this->record) ?? '—'))
                        ->required()
                        ->rows(12)
                        ->default(fn (): ?string => $this->draft),
                    TextInput::make('subject')
                        ->label('Onderwerp (optioneel)'),
                ])
                ->action(function (array $data) {
                    try {
                        $email = $this->replyService()->send($this->record, $data['message'], $data['subject'] ?? null);
                    } catch (\Throwable $e) {
                        Notification::make()->title('Versturen mislukt')->body($e->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Antwoord verstuurd naar ' . $email)->success()->send();

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
                $name = 'content_'.Str::slug((string) $key, '_');

                $inputFields[] = TextEntry::make($name)
                    ->label($label)
                    ->state(is_array($value) ? json_encode($value) : $value);
            }
        } else {
            foreach ($this->record->formFields as $field) {
                $id = (string) ($field->formField->id ?? Str::random(8));
                $name = 'field_'.$id;

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
                            $inputFields[] = TextEntry::make($name.'_download')
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
                                ->url(fn () => (is_string($this->record->from_url) && (str_starts_with($this->record->from_url, 'http://') || str_starts_with($this->record->from_url, 'https://'))) ? $this->record->from_url : null)
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
