<?php

namespace Qubiqx\QcommerceForms\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Form extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'form_id',
        'ip',
        'user_agent',
        'content',
        'from_url',
        'site_id',
        'locale',
        'viewed',
    ];

    protected $table = 'qcommerce__forms';

    public function inputs()
    {
        return $this->hasMany(FormInput::class);
    }
}
