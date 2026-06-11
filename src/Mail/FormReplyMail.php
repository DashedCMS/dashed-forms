<?php

declare(strict_types=1);

namespace Dashed\DashedForms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedForms\Models\FormInput;

/**
 * Vrij-tekst antwoord op een formulier-aanvraag, verstuurd naar de inzender.
 * Bewust geen e-mailtemplate-systeem: de medewerker/AI levert de hele tekst aan.
 */
class FormReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public FormInput $formInput,
        public string $body,
        public string $subjectLine,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        $html = '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:15px;line-height:1.6;color:#111827;max-width:620px">'
            . nl2br(e($this->body))
            . '</div>';

        return new Content(htmlString: $html);
    }
}
