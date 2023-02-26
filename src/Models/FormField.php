<?php

namespace Qubiqx\QcommerceForms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\Concerns\HasMetadata;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceForms\Models\Form;
use Qubiqx\QcommercePages\Models\Page;
use Spatie\Translatable\HasTranslations;

class FormField extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $table = 'qcommerce__form_fields';

    public $translatable = [
        'name',
        'placeholder',
        'description',
        'helper_text',
        'options',
        'images',
    ];

    protected $casts = [
        'options' => 'array',
        'images' => 'array',
    ];

    protected $appends = [
        'fieldName'
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function getFieldNameAttribute(): string
    {
        return str($this->name)->slug() . '-' . $this->id;
    }

    public function isImage(): bool
    {
        return ($this->type == 'select-image' || $this->type == 'file' || ($this->type == 'input' && $this->input_type == 'file'));
    }
}
