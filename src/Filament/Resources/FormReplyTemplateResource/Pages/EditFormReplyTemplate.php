<?php

namespace Dashed\DashedForms\Filament\Resources\FormReplyTemplateResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedForms\Filament\Resources\FormReplyTemplateResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditFormReplyTemplate extends EditRecord
{
    use Translatable;

    protected static string $resource = FormReplyTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
