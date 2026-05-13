<?php

namespace Dashed\DashedForms\Models;

use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Form extends Model
{
    use HasCustomBlocks;
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__forms';

    /**
     * Minimal fillable: only the columns that Filament-driven admin code
     * needs to mass-assign. Existing call-sites that use `forceFill()` or
     * explicit property assignment keep working unchanged.
     */
    protected $fillable = [
        'enrollment_flow_id',
    ];

    public function enrollmentFlow(): BelongsTo
    {
        return $this->belongsTo(PopupFollowUpFlow::class, 'enrollment_flow_id');
    }

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
        'mustHaveSomethingDefined',
    ];

    protected $casts = [
        'external_options' => 'array',
        'redirect_after_form' => 'array',
        'notification_form_inputs_emails' => 'array',
        'webhooks' => 'array',
        'apis' => 'array',
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

    public function scopeSearch($query, ?string $search = null)
    {
        if (request()->get('search') ?: $search) {
            $search = strtolower(request()->get('search') ?: $search);

            return $query->where('name', 'LIKE', '%'.$search.'%');
        }
    }
}
