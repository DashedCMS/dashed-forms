<?php

namespace Qubiqx\QcommerceForms\Models;

use Illuminate\Database\Eloquent\Model;
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function scopeUnviewed($query)
    {
        $query->where('viewed', 0);
    }
}
