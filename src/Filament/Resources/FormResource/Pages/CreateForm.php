<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Resources\Pages\CreateRecord;

class CreateForm extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;
    protected static string $resource = FormResource::class;
}
