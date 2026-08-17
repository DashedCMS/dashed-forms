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
use Illuminate\Support\Facades\Storage;
use Dashed\DashedForms\Models\FormInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

    /**
     * Alle bestandsvelden van deze inzending, als [bestandsnaam => pad].
     * Dubbele bestandsnamen krijgen een volgnummer, anders overschrijft het
     * ene bestand het andere in de zip.
     */
    protected function attachmentPaths(): array
    {
        $paths = [];
        $seen = [];

        foreach ($this->record->formFields as $field) {
            if (! $field->formField?->isImage() || blank($field->value)) {
                continue;
            }

            $name = basename((string) $field->value);
            $seen[$name] = ($seen[$name] ?? 0) + 1;

            if ($seen[$name] > 1) {
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $base = pathinfo($name, PATHINFO_FILENAME);
                $name = $base . '-' . $seen[$name] . ($extension ? '.' . $extension : '');
            }

            $paths[$name] = (string) $field->value;
        }

        return $paths;
    }

    protected function getActions(): array
    {
        return [
            Action::make('downloadAttachments')
                ->button()
                ->color('gray')
                ->icon('heroicon-o-arrow-down-tray')
                ->label(fn (): string => __('Bijlagen downloaden') . ' (' . count($this->attachmentPaths()) . ')')
                ->visible(fn (): bool => count($this->attachmentPaths()) > 0)
                ->action(function () {
                    $paths = $this->attachmentPaths();

                    // Een enkel bestand hoeft niet ingepakt: dan is een zip
                    // alleen maar een extra handeling voor de ontvanger.
                    if (count($paths) === 1) {
                        $path = reset($paths);

                        return response()->streamDownload(
                            fn () => print (Storage::disk('dashed')->get($path)),
                            basename($path)
                        );
                    }

                    $zipPath = tempnam(sys_get_temp_dir(), 'form-attachments-') . '.zip';
                    $zip = new \ZipArchive();

                    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                        Notification::make()
                            ->title(__('Downloaden mislukt'))
                            ->body(__('Kon geen zip-bestand aanmaken.'))
                            ->danger()
                            ->send();

                        return null;
                    }

                    $added = 0;

                    foreach ($paths as $name => $path) {
                        // Een ontbrekend bestand mag de hele download niet
                        // blokkeren; de rest is nog steeds bruikbaar.
                        if (! Storage::disk('dashed')->exists($path)) {
                            continue;
                        }

                        $zip->addFromString($name, Storage::disk('dashed')->get($path));
                        $added++;
                    }

                    $zip->close();

                    if ($added === 0) {
                        @unlink($zipPath);

                        Notification::make()
                            ->title(__('Geen bijlagen gevonden'))
                            ->body(__('De bestanden staan niet meer op de opslag.'))
                            ->warning()
                            ->send();

                        return null;
                    }

                    if ($added < count($paths)) {
                        Notification::make()
                            ->title(__('Niet alle bijlagen gevonden'))
                            ->body(__(':added van :total bestanden zijn ingepakt.', [
                                'added' => $added,
                                'total' => count($paths),
                            ]))
                            ->warning()
                            ->send();
                    }

                    return response()->download(
                        $zipPath,
                        'inzending-' . $this->record->id . '-bijlagen.zip'
                    )->deleteFileAfterSend();
                }),
            Action::make('toggleViewed')
                ->button()
                ->label($this->record->viewed ? __('Markeer als niet bekeken') : __('Markeer als bekeken'))
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
                ->label(__('AI-concept opstellen'))
                ->visible(fn (): bool => $this->replyService()->aiAvailable() && $this->replyService()->recipientEmail($this->record) !== null)
                ->modalSubmitActionLabel(__('Genereer'))
                ->form([
                    Textarea::make('instructions')
                        ->label(__('Eigen input voor de AI (optioneel)'))
                        ->placeholder(__('Bijv. "bied excuses aan en zeg dat we morgen leveren" of laat leeg voor een standaard antwoord.'))
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->draft = $this->replyService()->generateDraft($this->record, $data['instructions'] ?? null);
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Kon geen concept maken'))->body($e->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title(__('Concept klaar — open "Antwoord versturen" om te controleren en te verzenden.'))->success()->send();
                }),
            Action::make('sendReply')
                ->button()
                ->color('success')
                ->icon('heroicon-o-paper-airplane')
                ->label(__('Antwoord versturen'))
                ->visible(fn (): bool => $this->replyService()->recipientEmail($this->record) !== null)
                ->modalSubmitActionLabel(__('Versturen'))
                ->form([
                    Textarea::make('message')
                        ->label(__('Antwoord'))
                        ->helperText(fn (): string => __('Wordt per e-mail verstuurd naar :email', ['email' => $this->replyService()->recipientEmail($this->record) ?? '—']))
                        ->required()
                        ->rows(12)
                        ->default(fn (): ?string => $this->draft),
                    TextInput::make('subject')
                        ->label(__('Onderwerp (optioneel)')),
                ])
                ->action(function (array $data) {
                    try {
                        $email = $this->replyService()->send($this->record, $data['message'], $data['subject'] ?? null);
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Versturen mislukt'))->body($e->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title(__('Antwoord verstuurd naar :email', ['email' => $email]))->success()->send();

                    return redirect()->route('filament.dashed.resources.forms.viewInput', [$this->record->form->id, $this->record->id]);
                }),
            Action::make('delete')
                ->button()
                ->requiresConfirmation()
                ->color('danger')
                ->label(__('Verwijderen'))
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
                                ->helperText(__('Klik de afbeelding om te openen'))
                                ->state($field->value);
                        } else {
                            $inputFields[] = TextEntry::make($name.'_download')
                                ->state($field->formField->name)
                                ->label(__('Download bestand'))
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
            ->label(__('Bekeken'))
            ->badge()
            ->formatStateUsing(fn (): string => $this->record->viewed ? 'Ja' : 'Nee')
            ->color(fn (): string => $this->record->viewed ? 'success' : 'danger');

        return $schema
            ->record($this->record)
            ->schema([
                Flex::make([
                    Section::make(__('Ingevoerde informatie'))
                        ->schema($inputFields)
                        ->columnSpanFull()
                        ->grow(),
                    Section::make(__('Overige informatie'))
                        ->schema([
                            TextEntry::make('ip')
                                ->label(__('IP'))
                                ->default('Onbekend'),
                            TextEntry::make('user_agent')
                                ->label(__('User agent'))
                                ->default('Onbekend'),
                            TextEntry::make('from_url')
                                ->label(__('Ingevoerd vanaf'))
                                ->url(fn () => (is_string($this->record->from_url) && (str_starts_with($this->record->from_url, 'http://') || str_starts_with($this->record->from_url, 'https://'))) ? $this->record->from_url : null)
                                ->openUrlInNewTab()
                                ->default('Onbekend'),
                            TextEntry::make('created_at')
                                ->label(__('Ingevoerd op'))
                                ->default('Onbekend'),
                            TextEntry::make('site_id')
                                ->label(__('Site ID'))
                                ->visible(count(Sites::getSites()) > 1)
                                ->default('Onbekend'),
                            TextEntry::make('locale')
                                ->label(__('Taal'))
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
