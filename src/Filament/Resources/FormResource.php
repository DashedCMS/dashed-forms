<?php

namespace Dashed\DashedForms\Filament\Resources;

use Dashed\DashedCore\Models\User;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Dashed\DashedForms\Classes\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use FilamentTiptapEditor\TiptapEditor;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedForms\Models\FormInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedForms\Enums\MailingProviders;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedForms\Classes\WebhookProviders\Ternair;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\EditForm;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\ListForm;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\ViewForm;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\CreateForm;
use Dashed\DashedForms\Filament\Resources\FormResource\Pages\ViewFormInput;

class FormResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = \Dashed\DashedForms\Models\Form::class;
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Formulieren';
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

    public static function form(Form $form): Form
    {
        $schema = [
            TextInput::make('name')
                ->label('Naam')
                ->maxLength(255)
                ->required(),
            Select::make('email_confirmation_form_field_id')
                ->label('Email bevestiging veld voor de klant')
                ->options(fn($record) => $record ? $record->fields()->where('type', 'input')->where('input_type', 'email')->pluck('name', 'id') : []),
            TagsInput::make("notification_form_inputs_emails")
                ->suggestions(User::where('role', 'admin')->pluck('email')->toArray())
                ->label('Emails om de bevestigingsmail van een formulier aanvraag naar te sturen')
                ->helperText('Vul hier de emails in waar de bevestigingsmail naartoe gestuurd moet worden, indien je dit leeg laat worden de standaard emails gebruikt')
                ->placeholder('Voer een email in')
                ->reactive(),
            Repeater::make('webhooks')
                ->label('Webhooks')
                ->schema([
                    TextInput::make('webhook_url')
                        ->label('Webhook URL')
                        ->helperText('Vul hier de URL in waar de webhook naartoe gestuurd moet worden')
                        ->reactive()
                        ->required(),
                    Select::make('webhook_class')
                        ->label('Webhook class')
                        ->options([
                            Ternair::class => 'Ternair',
                        ])
                        ->required()
                        ->reactive(),
                ]),
            linkHelper()->field('redirect_after_form', false, 'Redirect na formulier'),
        ];


        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $schema[] = Toggle::make("external_options.send_to_$provider->slug")
                    ->label('Verstuur naar ' . $provider->name)
                    ->reactive();
                $schema = array_merge($schema, $provider->getFormSchema());
            }
        }

        $schema = array_merge($schema, static::customBlocksTab(cms()->builder('formBlocks')));

        $repeaterSchema = [
            TextInput::make('name')
                ->label('Naam')
                ->maxLength(255)
                ->required(),
            Select::make('type')
                ->label('Type veld')
                ->options(Forms::availableInputTypes())
                ->required()
                ->reactive(),
            Select::make('input_type')
                ->label('Input type veld')
                ->options(Forms::availableInputTypesForInput())
                ->required(fn($get) => in_array($get('type'), ['input']))
                ->reactive()
                ->visible(fn($get) => in_array($get('type'), ['input'])),
            TextInput::make('placeholder')
                ->label('Placeholder')
                ->maxLength(255)
                ->visible(fn($get) => in_array($get('type'), ['input', 'textarea'])),
            TextInput::make('regex')
                ->label('Regex validatie')
                ->hintActions([
                    \Filament\Forms\Components\Actions\Action::make('testRegex')
                        ->label('Test regex')
                        ->url('https://regex101.com')
                        ->openUrlInNewTab(),
                ])
                ->helperText('Bij foutieve regex geeft het formulier een foutmelding bij versturen en wordt de invoer niet opgeslagen')
                ->maxLength(255)
                ->visible(fn($get) => in_array($get('type'), ['input'])),
            TextInput::make('helper_text')
                ->label('Helper tekst')
                ->helperText('Zet hier eventueel uitleg neer over dit veld')
                ->maxLength(255),
            Toggle::make('required')
                ->label('Verplicht in te vullen')
                ->visible(fn($get) => !in_array($get('type'), ['info', 'image'])),
            Toggle::make('stack_start')
                ->label('Start van de stack'),
            Toggle::make('stack_end')
                ->label('Einde van de stack'),
            TiptapEditor::make('description')
                ->label('Descriptie')
                ->required(fn($get) => in_array($get('type'), ['info']))
                ->visible(fn($get) => in_array($get('type'), ['info', 'select-image'])),
            Repeater::make('options')
                ->label('Opties')
                ->required(fn($get) => in_array($get('type'), ['checkbox', 'radio', 'select']))
                ->visible(fn($get) => in_array($get('type'), ['checkbox', 'radio', 'select']))
                ->reorderable()
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->maxLength(255)
                        ->required(),
                ]),
            Repeater::make('images')
                ->label('Afbeeldingen')
                ->required(fn($get) => in_array($get('type'), ['select-image']))
                ->visible(fn($get) => in_array($get('type'), ['select-image']))
                ->reorderable()
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->maxLength(255),
                    FileUpload::make('image')
                        ->label('Afbeelding')
                        ->required()
                        ->image()
                        ->directory('dashed/images'),
                ]),
            FileUpload::make('image')
                ->label('Afbeelding')
                ->required(fn($get) => in_array($get('type'), ['image']))
                ->visible(fn($get) => in_array($get('type'), ['image']))
                ->image()
                ->directory('dashed/images'),
        ];

        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $repeaterSchema = array_merge($repeaterSchema, $provider->getFormFieldSchema());
            }
        }

        $schema[] = Repeater::make('fields')
            ->relationship('fields')
            ->label('Velden')
            ->reorderable()
            ->orderColumn()
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop()
            ->cloneable()
            ->reactive()
            ->schema($repeaterSchema)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->columnSpan(2);

        return $form
            ->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->formatStateUsing(fn($state) => ucfirst($state))
                    ->sortable()
                    ->searchable(query: SearchQuery::make()),
                TextColumn::make('amount_of_requests')
                    ->label('Aantal aanvragen')
                    ->getStateUsing(fn($record) => $record->inputs->count()),
                TextColumn::make('amount_of_unviewed_requests')
                    ->label('Aantal openstaande aanvragen')
                    ->getStateUsing(fn($record) => $record->inputs()->unviewed()->count()),
            ])
            ->actions([
                EditAction::make()
                    ->button(),
                Action::make('viewInputs')
                    ->label('Bekijk aanvragen')
                    ->icon('heroicon-s-eye')
                    ->button()
                    ->color('primary')
                    ->url(fn($record) => route('filament.dashed.resources.forms.viewInputs', [$record])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
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
