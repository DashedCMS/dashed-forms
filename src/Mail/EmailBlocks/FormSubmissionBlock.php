<?php

namespace Dashed\DashedForms\Mail\EmailBlocks;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class FormSubmissionBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'form-submission';
    }

    public static function label(): string
    {
        return __('Formulier gegevens');
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-clipboard-document-list')
            ->schema([
                TextInput::make('title')
                    ->label(__('Kop boven de gegevens'))
                    ->default('Ingevoerde gegevens'),
            ]);
    }

    /**
     * Een bestandsveld bewaart alleen het pad op de schijf. Onbewerkt in een
     * mail zetten levert een regel op als "dashed/forms/form-aml-form-xyz.jpg":
     * geen link, en de ontvanger kan er niets mee. Hier maken we er de
     * volledige URL van.
     */
    protected static function publicUrl(string $value): ?string
    {
        if ($value === '' || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value ?: null;
        }

        try {
            return Storage::disk('dashed')->url($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function render(array $blockData, array $context): string
    {
        $formInput = $context['formInput'] ?? null;
        $rows = [];

        if (is_object($formInput) && method_exists($formInput, 'formFields') && $formInput->formFields?->isNotEmpty()) {
            foreach ($formInput->formFields as $field) {
                $label = $field->formField?->name;
                $label = is_array($label) ? ($label[app()->getLocale()] ?? reset($label)) : $label;
                $value = $field->value;

                if ($value === null || $value === '') {
                    continue;
                }

                $rows[] = [
                    'label' => (string) ($label ?: '-'),
                    'value' => (string) $value,
                    'url' => $field->formField?->isImage() ? self::publicUrl((string) $value) : null,
                ];
            }
        }

        if (empty($rows)) {
            $content = is_object($formInput) ? ($formInput->content ?? []) : (is_array($formInput) ? ($formInput['content'] ?? []) : []);

            foreach ((array) $content as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value));
                }

                if (! is_scalar($value) || $value === '') {
                    continue;
                }

                $rows[] = [
                    'label' => (string) $key,
                    'value' => (string) $value,
                ];
            }
        }

        return view('dashed-forms::emails.blocks.form-submission', [
            'title' => (string) ($blockData['title'] ?? 'Ingevoerde gegevens'),
            'rows' => $rows,
        ])->render();
    }
}
