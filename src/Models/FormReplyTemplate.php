<?php

namespace Dashed\DashedForms\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Classes\Locales;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Antwoord-template voor formulierinzendingen. Zonder formulieren (form_ids
 * leeg) geldt hij voor alle formulieren.
 */
class FormReplyTemplate extends Model
{
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'dashed__form_reply_templates';

    protected $fillable = ['name', 'subject', 'body', 'form_ids', 'order'];

    public $translatable = ['subject', 'body'];

    protected $casts = [
        'form_ids' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function scopeForForm(Builder $query, Form $form): Builder
    {
        return $query->where(function (Builder $q) use ($form) {
            $q->whereNull('form_ids')
                ->orWhereJsonLength('form_ids', 0)
                ->orWhereJsonContains('form_ids', $form->id)
                ->orWhereJsonContains('form_ids', (string) $form->id);
        });
    }

    public function renderSubject(string $locale): string
    {
        return trim(strip_tags($this->translated('subject', $locale)));
    }

    public function renderBody(string $locale): string
    {
        return $this->translated('body', $locale);
    }

    private function translated(string $field, string $locale): string
    {
        $value = $this->getTranslation($field, $locale, false);
        if (blank($value)) {
            $value = $this->getTranslation($field, Locales::getFirstLocale()['id'] ?? 'en', false);
        }

        if (is_array($value)) {
            return RichContentRenderer::make($value)->toHtml();
        }

        return (string) $value;
    }
}
