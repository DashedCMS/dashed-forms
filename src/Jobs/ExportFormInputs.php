<?php

namespace Dashed\DashedForms\Jobs;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedForms\Exports\ExportFormData;
use Dashed\DashedForms\Mail\FormInputsExportMail;

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
        $this->records = $records;
        $this->email = $email;
        $this->hash = Str::random();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Excel::store(new ExportFormData($this->records), '/dashed/tmp-exports/' . $this->hash . '/forms/form-data.xlsx', 'public');
        Mail::to($this->email)->send(new FormInputsExportMail($this->hash));
        Storage::disk('public')->deleteDirectory('/dashed/tmp-exports/' . $this->hash);
    }
}
