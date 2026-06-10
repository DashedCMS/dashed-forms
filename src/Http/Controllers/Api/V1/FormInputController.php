<?php

declare(strict_types=1);

namespace Dashed\DashedForms\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedForms\Models\Form;
use Dashed\DashedForms\Models\FormInput;

class FormInputController extends Controller
{
    /** Lijst met aanvragen (form-inputs) voor de actieve site. */
    public function index(Request $request): JsonResponse
    {
        $site = (string) Sites::getActive();

        $query = FormInput::where('site_id', $site)->with(['form', 'formFields.formField'])->latest();

        if ($request->filled('form_id')) {
            $query->where('form_id', (int) $request->query('form_id'));
        }
        if ($request->boolean('unviewed')) {
            $query->where('viewed', 0);
        }
        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where('content', 'LIKE', '%' . $search . '%');
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (FormInput $fi) => $this->summary($fi))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'unviewed_total' => FormInput::where('site_id', $site)->where('viewed', 0)->count(),
        ]);
    }

    /** Eén aanvraag met alle ingevulde velden. */
    public function show(int $formInput): JsonResponse
    {
        $site = (string) Sites::getActive();
        $fi = FormInput::where('site_id', $site)->with(['form', 'formFields.formField'])->findOrFail($formInput);

        return response()->json(['data' => $this->detail($fi)]);
    }

    /** Markeer een aanvraag als gezien. */
    public function markViewed(int $formInput): JsonResponse
    {
        $site = (string) Sites::getActive();
        $fi = FormInput::where('site_id', $site)->findOrFail($formInput);
        $fi->viewed = 1;
        $fi->save();

        return response()->json(['success' => true]);
    }

    /** Beschikbare formulieren (om op te filteren) — alleen die inzendingen hebben. */
    public function forms(): JsonResponse
    {
        $site = (string) Sites::getActive();
        $formIds = FormInput::where('site_id', $site)->distinct()->pluck('form_id');
        $forms = Form::whereIn('id', $formIds)->get();

        return response()->json([
            'data' => $forms->map(fn (Form $f) => ['id' => $f->id, 'name' => $f->name])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(FormInput $fi): array
    {
        return [
            'id' => $fi->id,
            'form_id' => $fi->form_id,
            'form_name' => $fi->form?->name,
            'summary' => $this->summaryText($fi),
            'viewed' => (bool) $fi->viewed,
            'created_at' => optional($fi->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(FormInput $fi): array
    {
        $fields = [];
        foreach ($fi->formFields as $ff) {
            $fields[] = [
                'label' => $ff->formField?->name ?: 'Veld',
                'value' => $this->stringify($ff->value),
            ];
        }
        // Oudere inzendingen zonder losse velden: val terug op de content-array.
        if (! $fields && is_array($fi->content)) {
            foreach ($fi->content as $label => $value) {
                $fields[] = ['label' => (string) $label, 'value' => $this->stringify($value)];
            }
        }

        return array_merge($this->summary($fi), [
            'ip' => $fi->ip,
            'locale' => $fi->locale,
            'from_url' => $fi->from_url,
            'fields' => $fields,
        ]);
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : (string) json_encode($v), $value));
        }

        return (string) json_encode($value);
    }

    /** Korte samenvatting voor de lijst: een e-mailachtige waarde of de eerste ingevulde waarde. */
    private function summaryText(FormInput $fi): ?string
    {
        $values = $fi->formFields->pluck('value')->all();
        if (! $values && is_array($fi->content)) {
            $values = array_values($fi->content);
        }

        foreach ($values as $value) {
            if (is_string($value) && str_contains($value, '@')) {
                return $value;
            }
        }
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
