<?php

namespace Qubiqx\QcommerceForms\Filament\Resources\FormResource\Pages;

use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Filament\Resources\Pages\Page;
use Filament\Tables\Actions\LinkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\BooleanColumn;
use Maatwebsite\Excel\Facades\Excel;
use Qubiqx\QcommerceForms\Exports\ExportFormData;
use Qubiqx\QcommerceForms\Models\FormInput;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource;

class ViewForm extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    public $record;

    protected static string $resource = FormResource::class;
    protected static string $view = 'qcommerce-forms::forms.pages.view-form';

    public function getTableSortColumn(): ?string
    {
        return 'viewed';
    }

    public function mount($record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function getTableQuery(): Builder
    {
        return $this->record->inputs()->getQuery();
    }

    protected function getTitle(): string
    {
        return "Aanvragen voor {$this->record->name}";
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('viewed')
                ->label('Bekeken')
                ->options([
                    '0' => 'Niet bekeken',
                    '1' => 'Bekeken',
                ]),
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'viewed';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'ASC';
    }

    protected function getTableColumns(): array
    {
        $tableColumns = [
            TextColumn::make('id')
                ->label('ID')
                ->sortable(),
        ];

        $formInput = $this->record->inputs()->first();
        $inputCount = 0;
        foreach ($formInput->content as $key => $item) {
            if ($inputCount < 4) {
                $tableColumns[] = TextColumn::make($key)
                    ->label(Str::of($key)->replace('_', ' ')->title())
                    ->getStateUsing(fn($record) => $record->content[$key] ?? 'Niet ingevuld');
            }
            $inputCount++;
        }

        $tableColumns[] =
            IconColumn::make('viewed')
                ->falseIcon('heroicon-o-eye-off')
                ->trueIcon('heroicon-o-eye')
                ->label('Bekeken')
                ->searchable([
                    'ip',
                    'user_agent',
                    'content',
                    'from_url',
                    'site_id',
                    'locale',
                ])
                ->sortable();

        return $tableColumns;
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('Bekijk')
                ->url(fn(FormInput $record): string => route('filament.resources.forms.viewFormInput', [$record->form->id, $record])),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('export')
                ->label('Exporteer')
                ->action(function (Collection $records) {
                    $this->notify('success', 'Resultaten geëxporteerd');
                    return Excel::download(new ExportFormData($records), 'form-data.xlsx');
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }
}
