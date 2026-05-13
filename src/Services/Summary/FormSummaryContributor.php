<?php

declare(strict_types=1);

namespace Dashed\DashedForms\Services\Summary;

use Dashed\DashedCore\Services\Summary\Contracts\SummaryContributorInterface;
use Dashed\DashedCore\Services\Summary\DTOs\SummaryPeriod;
use Dashed\DashedCore\Services\Summary\DTOs\SummarySection;
use Dashed\DashedForms\Models\Form;
use Dashed\DashedForms\Models\FormInput;

/**
 * Samenvatting-bijdrage voor formulier-inzendingen. Toont het totaal
 * aantal nieuwe inzendingen in de periode plus een tabel met de
 * verdeling per formulier.
 */
class FormSummaryContributor implements SummaryContributorInterface
{
    public static function key(): string
    {
        return 'forms';
    }

    public static function label(): string
    {
        return 'Formulieren';
    }

    public static function description(): string
    {
        return 'Aantal nieuwe formulier-inzendingen in de periode, gegroepeerd per formulier.';
    }

    public static function defaultFrequency(): string
    {
        return 'weekly';
    }

    public static function availableFrequencies(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public static function contribute(SummaryPeriod $period): ?SummarySection
    {
        // Totaal aantal inzendingen in de periode. whereBetween op
        // created_at gebruikt de standaard timestamp-index.
        $totalSubmissions = FormInput::query()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();

        // Geen inzendingen, sla de sectie over zodat de mail niet
        // onnodig groter wordt.
        if ($totalSubmissions === 0) {
            return null;
        }

        // Per formulier groeperen om een verdeling te kunnen tonen.
        // form_id is geindexeerd via de foreign key constraint.
        $perForm = FormInput::query()
            ->selectRaw('form_id, COUNT(*) as submission_count')
            ->whereBetween('created_at', [$period->start, $period->end])
            ->groupBy('form_id')
            ->orderByDesc('submission_count')
            ->get();

        // Form-namen in een keer ophalen zodat we niet per rij een
        // extra query doen. withTrashed() ontbreekt bewust: een
        // verwijderd formulier valt buiten de samenvatting.
        $formNames = Form::query()
            ->whereIn('id', $perForm->pluck('form_id')->all())
            ->pluck('name', 'id');

        $rows = [];
        foreach ($perForm as $row) {
            $rows[] = [
                $formNames[$row->form_id] ?? 'Onbekend formulier',
                (string) $row->submission_count,
            ];
        }

        $blocks = [
            [
                'type' => 'stats',
                'data' => [
                    'rows' => [
                        ['label' => 'Nieuwe inzendingen', 'value' => (string) $totalSubmissions],
                    ],
                ],
            ],
        ];

        if (! empty($rows)) {
            $blocks[] = [
                'type' => 'table',
                'data' => [
                    'headers' => ['Formulier', 'Aantal inzendingen'],
                    'rows' => $rows,
                ],
            ];
        }

        return new SummarySection(
            title: 'Formulieren',
            blocks: $blocks,
        );
    }
}
