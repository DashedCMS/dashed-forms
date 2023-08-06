<?php

namespace Dashed\DashedForms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Form extends Model
{
    use LogsActivity;
    use HasTranslations;

    protected static $logFillable = true;

    protected $table = 'dashed__forms';

    protected static function booted()
    {
        static::deleting(function ($form) {
            $form->fields()->delete();
            $form->inputs()->delete();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public $translatable = [
        '',
    ];

    protected $casts = [
        'external_options' => 'array',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)
            ->orderBy('sort');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(FormInput::class);
    }

    public function emailConfirmationFormField(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'email_confirmation_form_field_id');
    }
}
