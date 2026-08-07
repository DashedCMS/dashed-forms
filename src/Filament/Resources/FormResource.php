<?php

namespace Dashed\DashedForms\Filament\Resources;

use Closure;
use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Dashed\DashedCore\Models\User;
use Filament\Actions\DeleteAction;
use Dashed\DashedForms\Models\Form;
use Dashed\DashedForms\Classes\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedForms\Models\FormInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Dashed\DashedForms\Enums\MailingProviders;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\EditForm;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\ListForm;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\ViewForm;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\CreateForm;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\ViewFormInput;

class FormResource extends Resource
{
    use HasCustomBlocksTab;
    use Translatable;

    protected static ?string $model = Form::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Formulieren';

    protected static ?string $label = 'Formulier';

    protected static ?string $pluralLabel = 'Formulieren';

    protected static bool $isGloballySearchable = false;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return 'Formulieren';
    }

    public static function getNavigationBadge(): ?string
    {
        return FormInput::unviewed()->count();
    }

    public static function form(Schema $schema): Schema
    {
        $apiFields = [];

        foreach (forms()->builder('apiClasses') as $api) {
            foreach ($api['class']::formFields() as $field) {
                $apiFields[] = $field
                    ->visible(fn (Get $get) => $get('class') == $api['class']);
            }
        }

        $newSchema = [
            TextInput::make('name')
                ->label(__('Naam'))
                ->maxLength(255)
                ->required(),
            Select::make('email_confirmation_form_field_id')
                ->label(__('Email bevestiging veld voor de klant'))
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->where('input_type', 'email')->pluck('name', 'id') : []),
            Select::make('enrollment_flow_id')
                ->label(__('Marketing-flow voor inschrijving na inzending'))
                ->helperText(__('Stuur een inzender automatisch door naar deze opvolg-flow nadat ze het formulier hebben verzonden.'))
                ->options(fn () => class_exists(PopupFollowUpFlow::class)
                    ? PopupFollowUpFlow::query()->orderBy('name')->pluck('name', 'id')->toArray()
                    : [])
                ->placeholder(__('Geen flow'))
                ->searchable()
                ->preload()
                ->nullable(),
            TagsInput::make('notification_form_inputs_emails')
                ->suggestions(User::where('role', 'admin')->pluck('email')->toArray())
                ->label(__('Emails om de bevestigingsmail van een formulier aanvraag naar te sturen'))
                ->helperText(__('Vul hier de emails in waar de bevestigingsmail naartoe gestuurd moet worden, indien je dit leeg laat worden de standaard emails gebruikt'))
                ->placeholder(__('Voer een email in'))
                ->reactive(),
            Repeater::make('webhooks')
                ->label(__('Webhooks'))
                ->visible(count(forms()->builder('webhookClasses')))
                ->schema([
                    TextInput::make('url')
                        ->label(__('Webhook URL'))
                        ->helperText(__('Vul hier de URL in waar de webhook naartoe gestuurd moet worden'))
                        ->reactive()
                        ->required(),
                    Select::make('class')
                        ->label(__('Webhook class'))
                        ->options(collect(forms()->builder('webhookClasses'))->pluck('name', 'class')->toArray())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive(),
                ])
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]),
            Repeater::make('apis')
                ->label(__('APIs'))
                ->visible(count(forms()->builder('apiClasses')))
                ->reactive()
                ->schema(fn (Get $get) => array_merge([
                    Select::make('class')
                        ->label(__('API class'))
                        ->options(collect(forms()->builder('apiClasses'))->pluck('name', 'class')->toArray())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive(),
                ], $apiFields))
                ->addActionLabel(__('API toevoegen'))
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]),
            linkHelper()->field('redirect_after_form', false, 'Redirect na formulier'),
        ];

        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $schema[] = Toggle::make("external_options.send_to_$provider->slug")
                    ->label(__('Verstuur naar :naam', ['naam' => $provider->name]))
                    ->reactive();
                $schema = array_merge($schema, $provider->getFormSchema());
            }
        }

        $newSchema = array_merge([
            Section::make()->columnSpanFull()
                ->schema($newSchema),
        ], static::customBlocksTab('formBlocks'));

        $repeaterSchema = [
            TextInput::make('name')
                ->label(__('Naam'))
                ->maxLength(255)
                ->required(),
            Select::make('type')
                ->label(__('Type veld'))
                ->options(Forms::availableInputTypes())
                ->required()
                ->reactive(),
            Select::make('input_type')
                ->label(__('Input type veld'))
                ->options(Forms::availableInputTypesForInput())
                ->required(fn ($get) => in_array($get('type'), ['input']))
                ->reactive()
                ->visible(fn ($get) => in_array($get('type'), ['input'])),
            TextInput::make('placeholder')
                ->label(__('Placeholder'))
                ->maxLength(255)
                ->visible(fn ($get) => in_array($get('type'), ['input', 'textarea'])),
            TextInput::make('regex')
                ->label(__('Regex validatie'))
                ->hintActions([
                    Action::make('testRegex')
                        ->label(__('Test regex'))
                        ->url('https://regex101.com')
                        ->openUrlInNewTab(),
                ])
                ->rules([
                    fn (): Closure => function (string $attribute, $value, Closure $fail) {
                        try {
                            // Test the regex by attempting to compile it
                            preg_match($value, '');
                        } catch (\Throwable $e) {
                            $fail("The $attribute is not a valid regular expression.");
                        }
                    },
                ])
                ->helperText(__('Bij foutieve regex geeft het formulier een foutmelding bij versturen en wordt de invoer niet opgeslagen'))
                ->maxLength(255)
                ->visible(fn ($get) => in_array($get('type'), ['input'])),
            TextInput::make('helper_text')
                ->label(__('Helper tekst'))
                ->helperText(__('Zet hier eventueel uitleg neer over dit veld'))
                ->maxLength(255),
            Toggle::make('required')
                ->label(__('Verplicht in te vullen'))
                ->visible(fn ($get) => ! in_array($get('type'), ['info', 'image'])),
            Toggle::make('stack_start')
                ->label(__('Start van de stack')),
            Toggle::make('stack_end')
                ->label(__('Einde van de stack')),
            cms()->editorField('description', 'Descriptie')
                ->required(fn ($get) => in_array($get('type'), ['info']))
                ->visible(fn ($get) => in_array($get('type'), ['info', 'select-image'])),
            Repeater::make('options')
                ->label(__('Opties'))
                ->required(fn ($get) => in_array($get('type'), ['checkbox', 'radio', 'select']))
                ->visible(fn ($get) => in_array($get('type'), ['checkbox', 'radio', 'select']))
                ->reorderable()
                ->schema([
                    TextInput::make('name')
                        ->label(__('Naam'))
                        ->maxLength(255)
                        ->required(),
                ]),
            Repeater::make('images')
                ->label(__('Afbeeldingen'))
                ->required(fn ($get) => in_array($get('type'), ['select-image']))
                ->visible(fn ($get) => in_array($get('type'), ['select-image']))
                ->reorderable()
                ->schema([
                    TextInput::make('name')
                        ->label(__('Naam'))
                        ->maxLength(255),
                    FileUpload::make('image')
                        ->label(__('Afbeelding'))
                        ->required()
                        ->image()
                        ->directory('dashed/images'),
                ]),
            FileUpload::make('image')
                ->label(__('Afbeelding'))
                ->required(fn ($get) => in_array($get('type'), ['image']))
                ->visible(fn ($get) => in_array($get('type'), ['image']))
                ->image()
                ->directory('dashed/images'),
        ];

        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $repeaterSchema = array_merge($repeaterSchema, $provider->getFormFieldSchema());
            }
        }

        $newSchema[] = Repeater::make('fields')
            ->relationship('fields')
            ->label(__('Velden'))
            ->reorderable()
            ->orderColumn()
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop()
            ->cloneable()
            ->reactive()
            ->schema(array_merge($repeaterSchema, static::customBlocksTab('formFieldBlocks')))
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->columnSpan(2);

        return $schema
            ->schema($newSchema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable()
                    ->searchable(query: SearchQuery::make()),
                TextColumn::make('amount_of_requests')
                    ->label(__('Aantal aanvragen'))
                    ->getStateUsing(fn ($record) => $record->inputs->count()),
                TextColumn::make('amount_of_unviewed_requests')
                    ->label(__('Aantal openstaande aanvragen'))
                    ->getStateUsing(fn ($record) => $record->inputs()->unviewed()->count()),
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
                Action::make('viewInputs')
                    ->label(__('Bekijk aanvragen'))
                    ->icon('heroicon-s-eye')
                    ->button()
                    ->color('primary')
                    ->url(fn ($record) => route('filament.dashed.resources.forms.viewInputs', [$record])),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions())
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForm::route('/'),
            'create' => CreateForm::route('/create'),
            'edit' => EditForm::route('/{record}/edit'),
            'viewInputs' => ViewForm::route('/{record}/inputs'),
            'viewInput' => ViewFormInput::route('/{record}/inputs/{formInput}'),
        ];
    }
}
