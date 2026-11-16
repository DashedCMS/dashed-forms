<?php

namespace Dashed\DashedForms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Dashed\DashedCore\Security\Uploads\PrivateUploads;

/**
 * Verhuist formulierbestanden die nog publiek staan (dashed/forms op de
 * media-disk, of form-uploads/ op de lokale disk uit een eerdere
 * rechargehamster-versie) naar de privé opslag van dashed-core en zet het
 * nieuwe pad in dashed__form_input_fields. Daarna gaan losse bestanden in
 * dashed/forms die bij geen enkele inzending horen ook privé
 * (forms-unlinked), zodat er niets publiek blijft staan.
 *
 * Gestart door de migratie privatize_form_uploads (alleen in productie) of
 * met `php artisan dashed-forms:privatize-uploads`. Veilig om opnieuw te
 * draaien: verwerkte waarden beginnen met private-uploads/ en worden
 * overgeslagen. Per run een tijdsbudget; is er meer, dan plant de job zich
 * opnieuw in.
 */
class PrivatizeFormUploads implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    /** Seconden werk per run, ruim onder de timeout. */
    public const BUDGET_SECONDS = 600;

    /** @var array<string, string> voorvoegsel => disk */
    public const SOURCES = [
        'dashed/forms/' => 'dashed',
        'form-uploads/' => 'local',
    ];

    /**
     * Nooit twee runs tegelijk; een vervolgrun die de job zelf inplant komt
     * daardoor gewoon achter de lopende aan.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping(static::class))->releaseAfter(120)->expireAfter(1200)];
    }

    public function handle(): void
    {
        $started = microtime(true);
        $stats = ['moved' => 0, 'missing' => 0, 'skipped' => 0, 'unlinked' => 0];

        $done = $this->privatizeLinkedValues($started, $stats)
            && $this->privatizeUnlinkedFiles($started, $stats);

        Log::info('[dashed-forms] formulierbestanden naar privé opslag', $stats + ['klaar' => $done]);

        if (! $done) {
            static::dispatch()->delay(now()->addMinute());
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    protected function privatizeLinkedValues(float $started, array &$stats): bool
    {
        $query = DB::table('dashed__form_input_fields')->where(function ($query) {
            foreach (array_keys(static::SOURCES) as $prefix) {
                $query->orWhere('value', 'like', $prefix . '%');
            }
        });

        foreach ($query->orderBy('id')->lazyById(200) as $row) {
            if (microtime(true) - $started > static::BUDGET_SECONDS) {
                return false;
            }

            [$disk, $path] = static::sourceFor((string) $row->value);

            $target = PrivateUploads::moveIntoPrivate($disk, $path, 'forms', deleteSource: false);
            if (! $target) {
                // Ontbrekend bestand of onbekend type: de waarde blijft ongewijzigd staan.
                $stats[Storage::disk($disk)->exists($path) ? 'skipped' : 'missing']++;

                continue;
            }

            DB::table('dashed__form_input_fields')->where('id', $row->id)->update(['value' => $target]);

            // Origineel pas weg als geen andere inzending er nog naar wijst.
            if (! DB::table('dashed__form_input_fields')->where('value', $row->value)->exists()) {
                rescue(fn () => Storage::disk($disk)->delete($path), null, false);
            }

            $stats['moved']++;
        }

        return true;
    }

    /**
     * @param  array<string, int>  $stats
     */
    protected function privatizeUnlinkedFiles(float $started, array &$stats): bool
    {
        foreach (static::SOURCES as $prefix => $disk) {
            $directory = rtrim($prefix, '/');
            $files = rescue(fn () => Storage::disk($disk)->files($directory), [], false);

            foreach ($files as $path) {
                if (microtime(true) - $started > static::BUDGET_SECONDS) {
                    return false;
                }

                if (DB::table('dashed__form_input_fields')->where('value', $path)->exists()) {
                    continue;
                }

                if (PrivateUploads::moveIntoPrivate($disk, $path, 'forms-unlinked')) {
                    $stats['unlinked']++;
                } elseif (Storage::disk($disk)->exists($path)) {
                    // Type dat de privé opslag niet kent (bv. een probe-bestand): weg uit de publieke map.
                    rescue(fn () => Storage::disk($disk)->delete($path), null, false);
                    $stats['skipped']++;
                }
            }
        }

        return true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function sourceFor(string $value): array
    {
        foreach (static::SOURCES as $prefix => $disk) {
            if (str_starts_with($value, $prefix)) {
                return [$disk, $value];
            }
        }

        return ['dashed', $value];
    }
}
