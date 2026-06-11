<?php

declare(strict_types=1);

namespace Dashed\DashedForms\Services;

use RuntimeException;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedForms\Mail\FormReplyMail;

/**
 * Genereert en verstuurt een (AI-)antwoord op een formulier-aanvraag. Gedeeld
 * door de mobile-API (app) en de Filament-pagina (CMS) zodat beide exact
 * dezelfde logica gebruiken.
 */
class FormReplyService
{
    private const AI = '\Dashed\DashedAi\Facades\Ai';

    /** Het e-mailadres van de inzender (eerste geldige e-mailwaarde in de inzending). */
    public function recipientEmail(FormInput $formInput): ?string
    {
        foreach ($this->values($formInput) as $value) {
            if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                return trim($value);
            }
        }

        return null;
    }

    public function aiAvailable(): bool
    {
        return class_exists(self::AI);
    }

    /**
     * Stel met AI een concept-antwoord op. $instructions = optionele eigen input
     * van de medewerker die de AI moet volgen. De tone-of-voice brief wordt door
     * de AI-laag automatisch meegestuurd.
     */
    public function generateDraft(FormInput $formInput, ?string $instructions = null): string
    {
        if (! $this->aiAvailable()) {
            throw new RuntimeException('AI is niet beschikbaar in deze omgeving.');
        }

        $formName = $formInput->form?->name ?: 'formulier';
        $lines = [];
        foreach ($this->fields($formInput) as $label => $value) {
            $lines[] = $label . ': ' . $value;
        }

        $prompt = "Een klant heeft het formulier \"{$formName}\" op onze website ingevuld."
            . " Hieronder staat wat de klant heeft ingevuld.\n\nInzending:\n" . implode("\n", $lines);

        if ($instructions !== null && trim($instructions) !== '') {
            $prompt .= "\n\nExtra instructies van de medewerker (volg deze nauwgezet):\n" . trim($instructions);
        }

        $prompt .= "\n\nSchrijf een vriendelijk, professioneel en concreet antwoord in het Nederlands"
            . " dat wij per e-mail naar de klant sturen. Spreek de klant met de voornaam aan als die bekend is."
            . " Geef ALLEEN de e-mailtekst zelf terug: geen onderwerpregel, geen placeholders zoals [naam],"
            . ' en geen toelichting of opmaak-tekens.';

        $draft = (self::AI)::text($prompt);

        if (! is_string($draft) || trim($draft) === '') {
            throw new RuntimeException('De AI gaf geen antwoord. Controleer of er een AI-provider is gekoppeld.');
        }

        return trim($draft);
    }

    /** Verstuur het antwoord per e-mail naar de inzender en log dit. */
    public function send(FormInput $formInput, string $message, ?string $subject = null): string
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('Het antwoord is leeg.');
        }

        $email = $this->recipientEmail($formInput);
        if (! $email) {
            throw new RuntimeException('Geen e-mailadres gevonden in deze aanvraag.');
        }

        $subject = $subject !== null && trim($subject) !== ''
            ? trim($subject)
            : 'Reactie op je aanvraag — ' . ($formInput->form?->name ?: 'formulier');

        Mail::to($email)->send(new FormReplyMail($formInput, $message, $subject));

        activity()
            ->performedOn($formInput)
            ->withProperties(['email' => $email, 'subject' => $subject])
            ->log('aanvraag beantwoord (e-mail naar ' . $email . ')');

        // Beantwoorden impliceert gezien.
        $formInput->forceFill(['viewed' => 1])->save();

        return $email;
    }

    /** @return array<string, string> label => waarde */
    private function fields(FormInput $formInput): array
    {
        $out = [];
        foreach ($formInput->formFields as $ff) {
            $label = $ff->formField?->name ?: 'Veld';
            $out[$label] = $this->stringify($ff->value);
        }
        if (! $out && is_array($formInput->content)) {
            foreach ($formInput->content as $label => $value) {
                $out[(string) $label] = $this->stringify($value);
            }
        }

        return $out;
    }

    /** @return array<int, mixed> ruwe waarden (voor e-mail-detectie) */
    private function values(FormInput $formInput): array
    {
        $values = $formInput->formFields->pluck('value')->all();
        if (! $values && is_array($formInput->content)) {
            $values = array_values($formInput->content);
        }

        return $values;
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
}
