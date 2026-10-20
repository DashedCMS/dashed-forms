<?php

namespace Dashed\DashedForms\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een verstuurd antwoord op een formulierinzending, met de volledige inhoud
 * zoals die naar de inzender is gegaan. Zo is later terug te zoeken wat er
 * precies is gezegd, door wie en met welke CC.
 */
class FormReply extends Model
{
    protected $table = 'dashed__form_replies';

    protected $fillable = [
        'form_input_id',
        'form_reply_template_id',
        'user_id',
        'to_email',
        'cc',
        'subject',
        'body',
    ];

    protected $casts = [
        'cc' => 'array',
    ];

    public function formInput(): BelongsTo
    {
        return $this->belongsTo(FormInput::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormReplyTemplate::class, 'form_reply_template_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Afzender zoals getoond bij de inzending: naam van de gebruiker, anders 'Systeem'. */
    public function getSenderNameAttribute(): string
    {
        return (string) ($this->user?->name ?: __('Systeem'));
    }
}
