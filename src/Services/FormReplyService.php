<?php

declare(strict_types=1);

namespace Dashed\DashedForms\Services;

use Tiptap\Editor;
use RuntimeException;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedForms\Models\FormReply;
use Dashed\DashedForms\Mail\FormReplyMail;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedForms\Models\FormReplyTemplate;

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

    /**
     * Verstuur het antwoord per e-mail naar de inzender, sla het op bij de
     * inzending en log het. $message mag platte tekst zijn (textarea, AI,
     * mobiele app) of HTML (rich editor in het CMS); platte tekst wordt in
     * alinea's gezet. Variabelen worden hier bewust niet meer vervangen: dat
     * gebeurt bij het kiezen van een template, zodat een tijd als 10:30:00 in
     * een handgetypt antwoord niet per ongeluk als variabele wordt gezien.
     *
     * @param  array<int, string>  $cc
     */
    public function send(
        FormInput $formInput,
        string $message,
        ?string $subject = null,
        array $cc = [],
        ?FormReplyTemplate $template = null,
        ?int $userId = null,
    ): FormReply {
        $html = $this->toHtml($message);
        if (trim(strip_tags($html)) === '') {
            throw new RuntimeException('Het antwoord is leeg.');
        }

        $email = $this->recipientEmail($formInput);
        if (! $email) {
            throw new RuntimeException('Geen e-mailadres gevonden in deze aanvraag.');
        }

        $cc = array_values(array_unique(array_filter(array_map(
            fn ($address) => trim((string) $address),
            $cc,
        ), fn ($address) => $address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) && strcasecmp($address, $email) !== 0)));

        $subject = $subject !== null && trim($subject) !== ''
            ? trim($subject)
            : 'Reactie op je aanvraag — ' . ($formInput->form?->name ?: 'formulier');

        $mailer = Mail::to($email);
        if ($cc) {
            $mailer->cc($cc);
        }
        $mailer->send(new FormReplyMail($formInput, $html, $subject));

        $reply = FormReply::create([
            'form_input_id' => $formInput->id,
            'form_reply_template_id' => $template?->id,
            'user_id' => $userId,
            'to_email' => $email,
            'cc' => $cc ?: null,
            'subject' => $subject,
            'body' => $html,
        ]);

        activity()
            ->performedOn($formInput)
            ->withProperties(['email' => $email, 'cc' => $cc, 'subject' => $subject, 'form_reply_id' => $reply->id])
            ->log('aanvraag beantwoord (e-mail naar ' . $email . ($cc ? ', cc ' . implode(', ', $cc) : '') . ')');

        // Beantwoorden impliceert gezien.
        $formInput->forceFill(['viewed' => 1])->save();

        return $reply;
    }

    /** Het CC-adres voor antwoorden (instelling per site), of null. */
    public function ccAddress(FormInput $formInput): ?string
    {
        $address = trim((string) Customsetting::get('form_reply_cc_email', $formInput->site_id));

        return filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : null;
    }

    /**
     * Platte tekst wordt HTML met alinea's; iets dat al HTML is gaat door
     * zoals het is.
     */
    public function toHtml(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        if ($message !== strip_tags($message)) {
            return $message;
        }

        $paragraphs = preg_split("/\n{2,}/", str_replace("\r\n", "\n", $message)) ?: [];

        return implode('', array_map(
            fn (string $paragraph) => '<p>' . str_replace("\n", '<br>', e(trim($paragraph))) . '</p>',
            array_filter($paragraphs, fn (string $paragraph) => trim($paragraph) !== ''),
        ));
    }

    /**
     * HTML als state voor de rich editor van het CMS (Tiptap-document), zodat
     * een template of AI-concept in het antwoordveld geladen kan worden.
     */
    public function toEditorState(string $html): array
    {
        $html = $this->toHtml($html);
        if ($html === '') {
            return ['type' => 'doc', 'content' => []];
        }

        $document = (new Editor())->setContent($html)->getDocument();

        return [
            'type' => 'doc',
            'content' => cms()->cleanForFilamentRich(cms()->normalizeRichState($document)),
        ];
    }

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
