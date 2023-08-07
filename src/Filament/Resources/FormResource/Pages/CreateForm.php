<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedForms\Filament\Resources\FormResource;

class CreateForm extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;
    protected static string $resource = FormResource::class;
}
