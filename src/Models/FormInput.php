<?php

namespace Qubiqx\QcommerceForms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Qubiqx\QcommerceForms\Enums\MailingProviders;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FormInput extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'name',
    ];

    protected $table = 'qcommerce__form_inputs';

    protected $casts = [
        'content' => 'array',
    ];

    protected static function booted()
    {
        static::created(function ($formInput) {
            foreach (MailingProviders::cases() as $provider) {
                $provider = $provider->getClass();
                if ($provider->connected) {
                    $provider->createContactFromFormInput($formInput);
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(FormInputField::class);
    }

    public function scopeUnviewed($query)
    {
        $query->where('viewed', 0);
    }
}
