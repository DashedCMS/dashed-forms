<?php

namespace Dashed\DashedForms\Models;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Dashed\DashedCore\Models\Customsetting;
use Spatie\Activitylog\Traits\LogsActivity;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;

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
        'customer_mail_subject',
        'admin_mail_subject',
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

    /**
     * Het per-formulier ingestelde mailonderwerp met ingevulde variabelen, of
     * null als er niets is ingesteld en het standaard onderwerp moet gelden.
     *
     * Variabelen gebruiken de :naam:-vorm van EmailRenderer::renderSubject:
     * :formName: en :siteName: altijd, plus elke veldwaarde van de inzending
     * onder de geslugde veldnaam (veld "E-mailadres" wordt :e_mailadres:).
     * Onbekende variabelen blijven letterlijk staan, net als in de renderer.
     */
    public function resolveMailSubject(string $type, FormInput $formInput): ?string
    {
        $subject = $type === 'admin' ? $this->admin_mail_subject : $this->customer_mail_subject;

        if (blank($subject)) {
            return null;
        }

        $variables = [
            'formName' => (string) $this->name,
            'siteName' => (string) Customsetting::get('site_name'),
        ];

        foreach ($this->mailSubjectFieldValues($formInput) as $name => $value) {
            $slug = Str::slug((string) $name, '_');
            if ($slug !== '') {
                $variables[$slug] = $value;
            }
        }

        return preg_replace_callback(
            '/:(\w+):/',
            fn ($m) => array_key_exists($m[1], $variables) ? $variables[$m[1]] : $m[0],
            $subject
        );
    }

    /**
     * Veldnaam => waarde van een inzending, over beide opslagroutes: de
     * v2-route bewaart per veld een FormInputField, de legacy-route alleen
     * de content-array met de veldnaam als sleutel.
     */
    protected function mailSubjectFieldValues(FormInput $formInput): array
    {
        $values = [];

        foreach ($formInput->formFields as $inputField) {
            $name = $inputField->formField?->name;
            if (filled($name) && is_scalar($inputField->value)) {
                $values[(string) $name] = (string) $inputField->value;
            }
        }

        foreach ($formInput->content ?? [] as $name => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }
            if (! is_scalar($value) || array_key_exists((string) $name, $values)) {
                continue;
            }
            $values[(string) $name] = (string) $value;
        }

        return $values;
    }

    public function scopeSearch($query, ?string $search = null)
    {
        if (request()->get('search') ?: $search) {
            $search = strtolower(request()->get('search') ?: $search);

            return $query->where('name', 'LIKE', '%'.$search.'%');
        }
    }
}
