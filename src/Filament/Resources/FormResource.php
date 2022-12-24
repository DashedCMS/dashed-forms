<?php

namespace Qubiqx\QcommerceForms\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource\Pages\ListForm;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource\Pages\ViewForm;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource\Pages\ViewFormInput;
use Qubiqx\QcommerceForms\Models\FormInput;

class FormResource extends Resource
{
    protected static ?string $model = \Qubiqx\QcommerceForms\Models\Form::class;
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-archive';
    protected static ?string $navigationGroup = 'Formulieren';
    protected static ?string $label = 'Formulier';
    protected static ?string $pluralLabel = 'Formulieren';
    protected static bool $isGloballySearchable = false;

    protected static function getNavigationLabel(): string
    {
        return 'Formulieren (' . FormInput::unviewed()->count() . ')';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Menu')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount_of_requests')
                    ->label('Aantal aanvragen')
                    ->getStateUsing(fn ($record) => $record->inputs->count()),
                TextColumn::make('amount_of_unviewed_requests')
                    ->label('Aantal openstaande aanvragen')
                    ->getStateUsing(fn ($record) => $record->inputs()->unviewed()->count()),
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
            'view' => ViewForm::route('/{record}/inputs'),
            'viewFormInput' => ViewFormInput::route('/{record}/inputs/{formInput}'),
        ];
    }
}
