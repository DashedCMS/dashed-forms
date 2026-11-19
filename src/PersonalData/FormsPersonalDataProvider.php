<?php

declare(strict_types=1);

namespace Dashed\DashedForms\PersonalData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedCore\PersonalData\PersonalDataExport;
use Dashed\DashedCore\Security\Uploads\PrivateUploads;
use Dashed\DashedCore\PersonalData\PersonalDataFinding;
use Dashed\DashedCore\PersonalData\PersonalDataSubject;
use Dashed\DashedCore\PersonalData\PersonalDataProvider;

/**
 * Formulierinzendingen. Een inzending heeft geen e-mailkolom: het adres staat
 * als waarde van een veld (meestal een e-mailveld, maar welk veld is per
 * formulier anders). Een inzending hoort bij de persoon zodra één van zijn
 * veldwaarden precies dit adres is, of, bij oude inzendingen zonder losse
 * velden, het adres als waarde in de JSON van `content` staat. Daarnaast de
 * antwoorden die een beheerder naar dat adres stuurde.
 *
 * Verwijderen is echt verwijderen, ook van inzendingen die al in de
 * prullenbak stonden (soft delete), met de geüploade bestanden en de regels
 * in het activiteitenlogboek over die inzendingen (FormInput logt zijn
 * velden).
 */
class FormsPersonalDataProvider extends PersonalDataProvider
{
    public function key(): string
    {
        return 'dashed-forms';
    }

    public function label(): string
    {
        return __('Formulieren');
    }

    public function priority(): int
    {
        return 300;
    }

    public function find(PersonalDataSubject $subject): array
    {
        $ids = $this->inputIds($subject);
        $findings = [];

        if ($ids !== []) {
            $findings[] = PersonalDataFinding::delete('submissions', __('Formulierinzendingen'), count($ids));
            $files = count($this->files($ids));
            if ($files > 0) {
                $findings[] = PersonalDataFinding::delete('files', __('Geüploade bestanden'), $files);
            }
        }

        $replies = $this->repliesQuery($subject, $ids)?->count() ?? 0;
        if ($replies > 0) {
            $findings[] = PersonalDataFinding::delete('replies', __('Antwoorden op inzendingen'), $replies);
        }

        return $findings;
    }

    public function export(PersonalDataSubject $subject, PersonalDataExport $export): void
    {
        $ids = $this->inputIds($subject);

        if ($ids !== []) {
            $fields = $this->hasTable('dashed__form_input_fields')
                ? DB::table('dashed__form_input_fields as v')
                    ->leftJoin('dashed__form_fields as f', 'f.id', '=', 'v.form_field_id')
                    ->whereIn('v.form_input_id', $ids)
                    ->get(['v.form_input_id', 'f.name as field', 'v.value'])
                    ->groupBy('form_input_id')
                : collect();

            $rows = DB::table('dashed__form_inputs as i')
                ->leftJoin('dashed__forms as fo', 'fo.id', '=', 'i.form_id')
                ->whereIn('i.id', $ids)
                ->orderBy('i.id')
                ->get(['i.id', 'fo.name as form', 'i.content', 'i.ip', 'i.user_agent', 'i.from_url', 'i.site_id', 'i.locale', 'i.created_at'])
                ->map(function ($row) use ($fields) {
                    $row = (array) $row;
                    $row['form'] = $this->plain($row['form']);
                    $row['content'] = json_decode((string) $row['content'], true) ?? $row['content'];
                    $row['fields'] = collect($fields->get($row['id'], []))
                        ->map(fn ($field) => ['field' => $this->plain($field->field), 'value' => $field->value])
                        ->values()
                        ->all();

                    return $row;
                })
                ->all();

            $export->add('submissions', $rows);

            foreach ($this->files($ids) as $file) {
                $export->addFile('files', $file['disk'], $file['path']);
            }
        }

        if ($replies = $this->repliesQuery($subject, $ids)) {
            $export->add('replies', $this->rows($replies->orderBy('id'), ['user_id', 'form_reply_template_id']));
        }
    }

    public function erase(PersonalDataSubject $subject): array
    {
        $ids = $this->inputIds($subject);
        $findings = [];

        $replies = $this->repliesQuery($subject, $ids)?->delete() ?? 0;

        if ($ids !== []) {
            $files = 0;
            foreach ($this->files($ids) as $file) {
                rescue(fn () => Storage::disk($file['disk'])->delete($file['path']), null, false);
                $files++;
            }

            if ($this->hasTable('dashed__form_input_fields')) {
                DB::table('dashed__form_input_fields')->whereIn('form_input_id', $ids)->delete();
            }
            if ($this->hasTable('activity_log')) {
                DB::table('activity_log')
                    ->whereIn('subject_type', [FormInput::class, (new FormInput())->getMorphClass()])
                    ->whereIn('subject_id', $ids)
                    ->delete();
            }
            $deleted = DB::table('dashed__form_inputs')->whereIn('id', $ids)->delete();

            $findings[] = PersonalDataFinding::delete('submissions', __('Formulierinzendingen'), $deleted);
            if ($files > 0) {
                $findings[] = PersonalDataFinding::delete('files', __('Geüploade bestanden'), $files);
            }
        }

        if ($replies > 0) {
            $findings[] = PersonalDataFinding::delete('replies', __('Antwoorden op inzendingen'), $replies);
        }

        return $findings;
    }

    /**
     * Inzendingen met dit adres, ook die in de prullenbak staan.
     *
     * @return array<int, int>
     */
    protected function inputIds(PersonalDataSubject $subject): array
    {
        if (! $this->hasTable('dashed__form_inputs')) {
            return [];
        }

        $ids = collect();

        if ($this->hasTable('dashed__form_input_fields')) {
            $ids = $ids->merge($subject->whereEmail(DB::table('dashed__form_input_fields'), 'value')->pluck('form_input_id'));
        }

        // Oude inzendingen: alleen de JSON in `content`. Het adres staat daar
        // als een eigen waarde tussen aanhalingstekens.
        $needle = '%"' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $subject->email) . '"%';
        $query = DB::table('dashed__form_inputs')->whereNotNull('content');
        $driver = $query->getConnection()->getDriverName();
        $query = in_array($driver, ['mysql', 'mariadb'], true)
            ? $query->where('content', 'like', $needle)
            : $query->whereRaw("LOWER(content) LIKE ? ESCAPE '\\'", [$needle]);
        $ids = $ids->merge($query->pluck('id'));

        return $ids->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * Antwoorden naar dit adres of op een van de gevonden inzendingen.
     *
     * @param  array<int, int>  $ids
     */
    protected function repliesQuery(PersonalDataSubject $subject, array $ids): ?\Illuminate\Database\Query\Builder
    {
        if (! $this->hasTable('dashed__form_replies')) {
            return null;
        }

        return DB::table('dashed__form_replies')->where(function ($query) use ($subject, $ids) {
            $subject->whereEmail($query, 'to_email');
            if ($ids !== []) {
                $query->orWhereIn('form_input_id', $ids);
            }
        });
    }

    /**
     * Geüploade bestanden van de inzendingen: privé opslag, of de oude
     * publieke schijf voor bestanden van vóór de verhuizing.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{disk: string, path: string}>
     */
    protected function files(array $ids): array
    {
        if (! $this->hasTable('dashed__form_input_fields') || ! $this->hasTable('dashed__form_fields')) {
            return [];
        }

        return DB::table('dashed__form_input_fields as v')
            ->join('dashed__form_fields as f', 'f.id', '=', 'v.form_field_id')
            ->whereIn('v.form_input_id', $ids)
            ->where(fn ($q) => $q->where('f.type', 'file')->orWhere(fn ($q) => $q->where('f.type', 'input')->where('f.input_type', 'file')))
            ->pluck('v.value')
            ->flatMap(fn ($value) => is_array($decoded = json_decode((string) $value, true)) ? $decoded : [$value])
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->map(fn (string $path) => class_exists(PrivateUploads::class) && PrivateUploads::isPrivate($path)
                ? ['disk' => PrivateUploads::diskName(), 'path' => $path]
                : ['disk' => 'dashed', 'path' => $path])
            ->unique(fn ($file) => $file['disk'] . ':' . $file['path'])
            ->values()
            ->all();
    }

    /** Een vertaalbare naam (JSON) als platte tekst. */
    protected function plain(mixed $value): mixed
    {
        if (is_string($value) && is_array($decoded = json_decode($value, true))) {
            return $decoded[app()->getLocale()] ?? reset($decoded);
        }

        return $value;
    }
}
