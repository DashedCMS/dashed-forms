<?php

namespace Dashed\DashedForms\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Dashed\DashedForms\Models\Form;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedForms\Classes\FormVariables;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedForms\Models\FormReplyTemplate;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Filament\Columns\LocaleStatusColumn;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedForms\Filament\Resources\FormReplyTemplateResource\Pages\EditFormReplyTemplate;
use Dashed\DashedForms\Filament\Resources\FormReplyTemplateResource\Pages\ListFormReplyTemplates;
use Dashed\DashedForms\Filament\Resources\FormReplyTemplateResource\Pages\CreateFormReplyTemplate;

/**
 * Antwoord-templates voor formulierinzendingen: de tegenhanger van de
 * bestel-log-templates, maar met variabelen uit de inzending in plaats van
 * uit de order.
 */
class FormReplyTemplateResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;
    use Translatable;

    protected static ?string $model = FormReplyTemplate::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Formulieren';

    protected static ?string $navigationLabel = 'Formulier templates';

    protected static ?string $label = 'Formulier template';

    protected static ?string $pluralLabel = 'Formulier templates';

    protected static bool $isGloballySearchable = false;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('Formulier templates');
    }

    /**
     * De variabelen die in een template gebruikt kunnen worden: de vaste vier
     * plus de geslugde veldnamen van de gekozen formulieren. Zonder keuze
     * (template voor alle formulieren) alleen de vaste.
     *
     * @param  array<int, int|string>  $formIds
     * @return array<int, string>
     */
    public static function variablesForForms(array $formIds): array
    {
        $names = FormVariables::GENERAL;

        if ($formIds) {
            foreach (Form::whereIn('id', $formIds)->get() as $form) {
                foreach (FormVariables::forForm($form) as $name) {
                    if (! in_array($name, $names, true)) {
                        $names[] = $name;
                    }
                }
            }
        }

        return array_map(fn (string $name) => ':' . $name . ':', $names);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Algemeen'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Naam'))
                            ->helperText(__('Alleen zichtbaar in het CMS, bij het kiezen van een template.'))
                            ->required()
                            ->maxLength(100),
                        Select::make('form_ids')
                            ->label(__('Formulieren'))
                            ->helperText(__('Leeg = beschikbaar bij alle formulieren.'))
                            ->options(fn () => Form::orderBy('name')->pluck('name', 'id')->toArray())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live(),
                        TextInput::make('order')
                            ->label(__('Volgorde'))
                            ->helperText(__('Laag nummer staat bovenaan in de keuzelijst.'))
                            ->numeric()
                            ->default(0),
                    ]),
                Section::make(__('Template inhoud'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('variables')
                            ->label(__('Variabelen'))
                            ->helperText(fn (Get $get): string => $get('form_ids')
                                ? __('Variabelen uit de gekozen formulieren staan erbij; een veld dat de inzender leeg liet wordt leeg ingevuld.')
                                : __('Kies formulieren om hun veldvariabelen te zien.'))
                            ->state(fn (Get $get): string => implode(', ', static::variablesForForms(array_filter((array) $get('form_ids'))))),
                        TextInput::make('subject')
                            ->label(__('Onderwerp'))
                            ->required()
                            ->maxLength(200),
                        cms()->editorField('body', __('Inhoud'))
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->sortable()
                    ->searchable(query: SearchQuery::make()),
                LocaleStatusColumn::make('subject'),
                TextColumn::make('form_ids')
                    ->label(__('Formulieren'))
                    ->getStateUsing(fn (FormReplyTemplate $record): string => $record->formNames()
                        ? implode(', ', $record->formNames())
                        : __('Alle formulieren')),
                TextColumn::make('order')
                    ->label(__('Volgorde'))
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions())
            ->filters([
                TrashedFilter::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormReplyTemplates::route('/'),
            'create' => CreateFormReplyTemplate::route('/create'),
            'edit' => EditFormReplyTemplate::route('/{record}/edit'),
        ];
    }
}
