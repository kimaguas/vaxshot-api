<?php

namespace App\Mail;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public ?string $resolvedSubject   = null,
        public ?string $resolvedBody      = null,
        public ?string $resolvedSignature = null,
        public ?string $resolvedHeader    = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->resolvedSubject
                ?? "Quotation {$this->quotation->quotation_number} from VaxshotApp",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotation',
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.quotation', [
            'quotation'         => $this->quotation,
            'resolvedBody'      => $this->resolvedBody,
            'resolvedSignature' => $this->resolvedSignature,
            'resolvedHeader'    => $this->resolvedHeader,
        ])->setPaper('a4', 'portrait');

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                "{$this->quotation->quotation_number}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
