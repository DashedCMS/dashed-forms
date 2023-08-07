<?php

namespace Qubiqx\QcommerceForms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormInputField extends Model
{
    use HasFactory;

    protected $table = 'qcommerce__form_input_fields';

    public function formInput(): BelongsTo
    {
        return $this->belongsTo(FormInput::class);
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }

    public function isImage(): bool
    {
        return $this->formField->isImage();
    }
}
