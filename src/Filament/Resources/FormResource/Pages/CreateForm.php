<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateForm extends CreateRecord
{
    use Translatable;

    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
