<?php

namespace Dashed\DashedForms\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormInput extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected static $logFillable = true;

    protected $fillable = [
        'name',
    ];

    protected $table = 'dashed__form_inputs';

    protected $casts = [
        'content' => 'array',
    ];

    public static function booted()
    {
        static::creating(function (FormInput $formInput) {
            if ($formInput->form->webhooks) {
                $formInput->should_send_webhook = true;
            }
            if ($formInput->form->apis) {
                $formInput->should_send_api = true;
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

    public function sendWebhooks()
    {
        foreach ($this->form->webhooks as $webhook) {
            $webhook['class']::dispatch($this, $webhook);
        }
    }

    public function sendApis()
    {
        foreach ($this->form->apis as $api) {
            $api['class']::dispatch($this, $api);
        }
    }
}
