<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedForms\Models\FormField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Dashed\DashedForms\Filament\Resources\FormResource;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditForm extends EditRecord
{
    use Translatable;

    protected static string $resource = FormResource::class;

    /** Zoveel validatiemeldingen tonen we; daarna wordt het onleesbaar. */
    protected const MAX_REPORTED_VALIDATION_FAILURES = 6;

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

        $copiedFieldIds = [];

        foreach ($this->record->fields as $field) {
            $newField = $field->replicate();
            $newField->form_id = $newRecord->id;
            $newField->save();

            $copiedFieldIds[$field->id] = $newField->id;
        }

        // replicate() neemt de pointer letterlijk over, dus die wees nog naar
        // het veld van het bronformulier. Zo'n vreemde waarde staat niet in de
        // optielijst van de select en laat elke opslag van de kopie - en dus
        // ook elke taalwissel - stuklopen op validatie.
        if ($newRecord->email_confirmation_form_field_id) {
            $newRecord->email_confirmation_form_field_id = $copiedFieldIds[$newRecord->email_confirmation_form_field_id] ?? null;
            $newRecord->save();
        }

        return redirect(route('filament.dashed.resources.forms.edit', [$newRecord]));
    }

    /** Of dit veld-id bij het formulier hoort dat nu bewerkt wordt. */
    protected function ownsFormField($fieldId): bool
    {
        return $fieldId && $this->record->fields()->whereKey($fieldId)->exists();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['mustHaveSomethingDefined']);
        foreach ($data as $key => $value) {
            if (str($key)->contains('redirect_after_form')) {
                $key = str($key)->replace('redirect_after_form_', '');
                $data['redirect_after_form']['url_'.$key] = $data['redirect_after_form_'.$key] ?? '';
                unset($data['redirect_after_form_'.$key]);
            }
        }

        // Bestaan is niet genoeg: de select biedt alleen de eigen velden van
        // dit formulier aan, dus een veld van een ander formulier is hier net
        // zo ongeldig als een veld dat helemaal niet meer bestaat.
        if (! $this->ownsFormField($data['email_confirmation_form_field_id'] ?? null)) {
            $data['email_confirmation_form_field_id'] = null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($data['redirect_after_form'] ?? [] as $key => $value) {
            $data['redirect_after_form_'.str($key)->replace('url_', '')] = $value;
        }

        unset($data['redirect_after_form']);

        // Erfenis van formulieren die vóór de reparatie in duplicate() zijn
        // gekopieerd: de pointer wijst naar een veld van het bronformulier.
        // Hier weghalen, want validatie draait voor mutateFormDataBeforeSave()
        // en zou anders elke opslag - en elke taalwissel - blokkeren.
        if (! $this->ownsFormField($data['email_confirmation_form_field_id'] ?? null)) {
            $data['email_confirmation_form_field_id'] = null;
        }

        return parent::mutateFormDataBeforeFill($data);
    }

    public function updatingActiveLocale($newVal): void
    {
        $this->oldActiveLocale = $this->activeLocale;

        try {
            $this->save();
        } catch (ValidationException $exception) {
            // Taal met nog lege verplichte velden: niets opslaan, maar de
            // wissel wel doorlaten. Anders kom je een half ingevulde taal niet
            // meer uit zonder de pagina te herladen. De validatiemeldingen gaan
            // mee: zonder de veldnamen weet je niet waar je moet zijn, en de
            // oorzaak hoeft niet eens een lege vertaling te zijn.
            Notification::make()
                ->warning()
                ->title('Wijzigingen niet opgeslagen')
                ->body($this->validationFailureSummary($exception))
                ->persistent()
                ->send();
        }

        // De velden staan in een repeater met relatie; die wordt niet door de
        // locale-switcher meegenomen. Laat Filament hem opnieuw inladen in de
        // nieuwe taal: de translatable content driver leest per attribuut de
        // vertaling van activeLocale, en Filament bouwt daarbij zelf de
        // uuid-keys van de repeater-items (opties, afbeeldingen) op.
        //
        // Zelf waardes in $this->data zetten gaat mis: dan houden de
        // onderliggende repeater-velden hun oude keys, valt de validatie op
        // 'verplicht' om en zet de switcher activeLocale terug op de vorige
        // taal - waarna opslaan die vorige taal overschrijft met de inhoud van
        // de nieuwe.
        //
        // Livewire zet direct hierna dezelfde waarde definitief op de property.
        $this->activeLocale = $newVal;

        $this->form->loadStateFromRelationships(shouldHydrate: true);
        $this->resetValidation();
    }

    /**
     * De standaard afhandeling van de locale-switcher neemt de state van de
     * vorige taal over in de nieuwe en valideert die daarbij. Dat klopt hier
     * niet: updatingActiveLocale() heeft de vorige taal al opgeslagen en de
     * form-state al opnieuw ingeladen in de nieuwe taal. Bovendien draaide die
     * validatie bij een nog lege taal activeLocale terug naar de vorige taal,
     * terwijl het scherm de nieuwe taal toonde - waarna opslaan de vorige taal
     * overschreef met de inhoud van de nieuwe.
     */
    public function updatedActiveLocale(): void
    {
        //
    }

    /**
     * Leesbare samenvatting van wat de validatie tegenhield, voor in de
     * melding bij een taalwissel. Daar is geen plek om fouten bij de velden
     * zelf te tonen: het scherm staat na de wissel al in de nieuwe taal,
     * terwijl de fouten over de taal gaan die je net verliet.
     */
    public function validationFailureSummary(ValidationException $exception): string
    {
        $lines = [];

        foreach ($exception->errors() as $key => $messages) {
            $field = $this->describeFailingField($key);

            foreach ((array) $messages as $message) {
                $line = $field ? "{$field}: {$message}" : $message;
                // Bij een repeater herhaalt dezelfde melding zich per item;
                // alleen unieke regels zijn het tonen waard.
                $lines[$line] = $line;
            }
        }

        $lines = array_values($lines);

        if (! $lines) {
            return 'Vul de verplichte velden van deze taal in en sla daarna opnieuw op.';
        }

        $shown = array_slice($lines, 0, self::MAX_REPORTED_VALIDATION_FAILURES);
        $hidden = count($lines) - count($shown);

        if ($hidden > 0) {
            $shown[] = "… en nog {$hidden} andere.";
        }

        return implode(' • ', $shown);
    }

    /**
     * De naam van het formulierveld waar een validatiesleutel over gaat, of
     * null voor een sleutel die niet bij een veld hoort. De repeater sleutelt
     * bestaande items op 'record-<id>'; nieuwe items hebben een uuid en zijn
     * dus nog niet terug te zoeken.
     */
    protected function describeFailingField(string $key): ?string
    {
        if (! preg_match('/^data\.fields\.record-(\d+)\./', $key, $matches)) {
            return null;
        }

        $field = FormField::find($matches[1]);

        if (! $field) {
            return null;
        }

        // De naam kan juist in de taal ontbreken waar de fout over gaat, dus
        // pak de eerste taal die er wél een heeft.
        $names = collect($field->getTranslations('name'))
            ->filter(fn ($name) => filled($name));

        $name = $names->get($this->activeLocale) ?? $names->first();

        return filled($name) ? "Veld “{$name}”" : null;
    }
}
