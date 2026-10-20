<?php

namespace Dashed\DashedForms\Filament\Resources\FormReplyTemplateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedForms\Filament\Resources\FormReplyTemplateResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateFormReplyTemplate extends CreateRecord
{
    use Translatable;

    protected static string $resource = FormReplyTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
