<?php

namespace Dashed\DashedForms\Jobs;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedForms\Models\FormInput;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedCore\Models\BackgroundTask;
use Dashed\DashedForms\Exports\ExportFormData;
use Dashed\DashedForms\Mail\FormInputsExportMail;
use Dashed\DashedCore\Notifications\AdminNotifier;

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

    public ?int $userId = null;

    /**
     * De taak in het takenicoon van dashed-core. Een export van meer dan
     * 5000 resultaten wordt over meerdere jobs verdeeld; die tellen samen
     * één taak af, aangemaakt door beginTask() bij het starten.
     */
    public ?int $taskId = null;

    public const TASK_TYPE = 'form-export';

    /**
     * Create a new job instance.
     */
    public function __construct($records, string $email, ?int $userId = null, ?int $taskId = null)
    {
        $this->records = FormInput::whereIn('id', $records)->get();
        $this->email = $email;
        $this->hash = Str::random();
        $this->userId = $userId;
        $this->taskId = $taskId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Excel::store(new ExportFormData($this->records), '/dashed/tmp-exports/'.$this->hash.'/forms/form-data.xlsx', 'public');
        AdminNotifier::send(new FormInputsExportMail($this->hash), $this->email);
        Storage::disk('public')->deleteDirectory('/dashed/tmp-exports/'.$this->hash);

        $this->advanceTask($this->records->count());
    }

    /**
     * Pas na de laatste poging: een tussentijdse fout probeert de wachtrij
     * opnieuw en de taak blijft dan op bezig staan.
     */
    public function failed(Throwable $e): void
    {
        if ($this->taskId === null || ! class_exists(BackgroundTask::class)) {
            return;
        }

        rescue(fn () => BackgroundTask::query()->whereKey($this->taskId)->active()->first()?->fail($e), report: false);
    }

    /**
     * Achter class_exists, want een oudere dashed-core kent BackgroundTask
     * nog niet. Geeft null als er geen taak is; de export loopt dan gewoon.
     */
    public static function beginTask(int $total, ?int $userId): ?int
    {
        if ($total < 1 || ! class_exists(BackgroundTask::class)) {
            return null;
        }

        return rescue(
            fn () => BackgroundTask::begin(self::TASK_TYPE, __('Formulierinzendingen exporteren'), $total, $userId, notify: $userId !== null)->getKey(),
            null,
            report: false,
        );
    }

    /**
     * Telt in de database op en niet op het model: de jobs van één export
     * kunnen naast elkaar op meerdere workers lopen.
     */
    protected function advanceTask(int $by): void
    {
        if ($this->taskId === null || ! class_exists(BackgroundTask::class)) {
            return;
        }

        rescue(function () use ($by) {
            BackgroundTask::query()->whereKey($this->taskId)->active()->increment('done', $by);

            $task = BackgroundTask::query()->whereKey($this->taskId)->active()->first();

            if ($task && $task->total && $task->done >= $task->total) {
                $task->finish(__(':aantal resultaten gemaild naar :email.', [
                    'aantal' => $task->total,
                    'email' => $this->email,
                ]));
            }
        }, report: false);
    }
}
