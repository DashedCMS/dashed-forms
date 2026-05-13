<?php

namespace Dashed\DashedForms\Jobs;

use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedForms\Exports\ExportFormData;
use Dashed\DashedForms\Mail\FormInputsExportMail;
use Dashed\DashedForms\Models\FormInput;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportFormInputs implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;

    public $timeout = 1200;

    public $records;

    public string $email;

    public string $hash;

    /**
     * Create a new job instance.
     */
    public function __construct($records, string $email)
    {
        $this->records = FormInput::whereIn('id', $records)->get();
        $this->email = $email;
        $this->hash = Str::random();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Excel::store(new ExportFormData($this->records), '/dashed/tmp-exports/'.$this->hash.'/forms/form-data.xlsx', 'public');
        AdminNotifier::send(new FormInputsExportMail($this->hash), $this->email);
        Storage::disk('public')->deleteDirectory('/dashed/tmp-exports/'.$this->hash);
    }
}
