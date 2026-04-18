<?php

namespace Dashed\DashedForms\Mail\EmailBlocks;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class FormSubmissionBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'form-submission';
    }

    public static function label(): string
    {
        return 'Formulier gegevens';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-clipboard-document-list')
            ->schema([
                TextInput::make('title')
                    ->label('Kop boven de gegevens')
                    ->default('Ingevoerde gegevens'),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $formInput = $context['formInput'] ?? null;
        $content = is_object($formInput) ? ($formInput->content ?? []) : (is_array($formInput) ? ($formInput['content'] ?? []) : []);

        $rows = [];
        foreach ((array) $content as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value));
            }

            if (! is_scalar($value)) {
                continue;
            }

            $rows[] = [
                'label' => (string) $key,
                'value' => (string) $value,
            ];
        }

        return view('dashed-forms::emails.blocks.form-submission', [
            'title' => (string) ($blockData['title'] ?? 'Ingevoerde gegevens'),
            'rows' => $rows,
        ])->render();
    }
}
