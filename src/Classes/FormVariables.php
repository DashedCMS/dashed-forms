<?php

namespace Dashed\DashedForms\Classes;

use Illuminate\Support\Str;
use Dashed\DashedForms\Models\Form;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedForms\Models\FormField;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Variabelen uit een formulierinzending, voor onderwerpen en antwoord-templates.
 * Elk ingevuld veld wordt :slug_van_de_veldnaam: (naam in de standaardlocale,
 * zodat een vertaald label de variabele niet verandert). Bijlagen nooit.
 */
class FormVariables
{
    public const GENERAL = ['formName', 'siteName', 'submittedAt', 'submissionId'];

    /** @return array<string, string> */
    public static function forInput(FormInput $formInput): array
    {
        $form = $formInput->form;

        $variables = [
            'formName' => (string) ($form?->name ?? ''),
            'siteName' => (string) Customsetting::get('site_name', $formInput->site_id),
            'submittedAt' => $formInput->created_at?->format('d-m-Y H:i') ?? '',
            'submissionId' => (string) $formInput->id,
        ];

        // v2-opslag: één rij per veld.
        foreach ($formInput->formFields as $inputField) {
            $field = $inputField->formField;
            if (! $field || $field->isImage()) {
                continue;
            }

            $slug = self::slugFor($field);
            if ($slug === '' || array_key_exists($slug, $variables)) {
                continue;
            }

            $value = self::stringify($inputField->value);
            if ($value !== '') {
                $variables[$slug] = $value;
            }
        }

        // Legacy-opslag: content-array met de veldnaam als sleutel.
        foreach ($formInput->content ?? [] as $name => $value) {
            $slug = Str::slug((string) $name, '_');
            if ($slug === '' || array_key_exists($slug, $variables)) {
                continue;
            }

            $value = self::stringify($value);
            if ($value !== '') {
                $variables[$slug] = $value;
            }
        }

        return $variables;
    }

    /** @return array<int, string> */
    public static function forForm(Form $form): array
    {
        $names = self::GENERAL;

        foreach ($form->fields()->get() as $field) {
            if ($field->isImage()) {
                continue;
            }
            $slug = self::slugFor($field);
            if ($slug !== '' && ! in_array($slug, $names, true)) {
                $names[] = $slug;
            }
        }

        return $names;
    }

    public static function replaceText(string $text, array $variables): string
    {
        return preg_replace_callback(
            '/:(\w+):/',
            fn ($m) => array_key_exists($m[1], $variables) ? (string) $variables[$m[1]] : '',
            $text
        );
    }

    public static function replaceHtml(string $html, array $variables): string
    {
        return preg_replace_callback(
            '/:(\w+):/',
            fn ($m) => array_key_exists($m[1], $variables) ? e((string) $variables[$m[1]]) : '',
            $html
        );
    }

    private static function slugFor(FormField $field): string
    {
        $defaultLocale = Locales::getFirstLocale()['id'] ?? app()->getLocale();
        $name = $field->getTranslation('name', $defaultLocale, true);

        return Str::slug((string) $name, '_');
    }

    private static function stringify(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return implode(', ', array_map('strval', array_filter($value, 'is_scalar')));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
