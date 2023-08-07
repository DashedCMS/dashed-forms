<?php

namespace Qubiqx\QcommerceForms\Filament\Resources\FormResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource;

class CreateForm extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;
    protected static string $resource = FormResource::class;
}
