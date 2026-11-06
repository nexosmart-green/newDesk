<?php

namespace App\Domain\Support\Notifications;

use App\Domain\Shared\Text;
use App\Domain\StatusPage\Status\ComponentState;
use App\Domain\Support\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A new problem report for the team (monitoreo Reports.notify, D-041), with all its context and
 * `Reply-To` the client, so answering the mail answers them. "[URGENTE]" when they cannot work.
 * The caller checks `newdesk.monitoring.notify` first (CA-13.20).
 */
final class ReportSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $body,
        public readonly string $clientEmail,
    ) {
        $this->onQueue('mail');
    }

    /**
     * The report needs `client` and `project` loaded.
     *
     * @param  array<string, mixed>  $live
     */
    public static function for(Report $report, array $live, string $reportUrl): self
    {
        $e = fn (?string $text) => e((string) $text);
        $where = $report->partLabel() ?? 'No sabe';
        $urgency = Report::URGENCY_LABELS[$report->urgency];
        $browser = $report->browser;

        $html = '<div style="font-family:system-ui,sans-serif;font-size:15px;color:#111;line-height:1.5">';
        $html .= sprintf('<p><strong>%s</strong> · %s<br>%s · <strong>%s</strong> · %s</p>', $e($report->client->name), $e($report->project->name),
            $e($report->user_email), $e($urgency), $e($where));
        $html .= sprintf('<p style="white-space:pre-wrap;background:#f5f5f7;padding:12px;border-radius:8px">%s</p>', $e($report->description));
        $html .= sprintf('<p><strong>Chequeo en vivo:</strong> %s</p><ul>', $e($live['message']));
        foreach ($live['components'] as $component) {
            $html .= sprintf('<li>%s: %s <span style="color:#666">(%s)</span></li>', $e($component['label']),
                $e(ComponentState::LABELS[$component['status']]), $e($component['detail']));
        }
        $html .= '</ul>';
        if ($report->recent_errors !== null) {
            $html .= '<p><strong>Errores recientes del proyecto</strong></p><ul>';
            if ($report->recent_errors->error !== null) {
                $html .= sprintf('<li>No se pudieron traer: %s</li>', $e($report->recent_errors->error));
            }
            foreach ($report->recent_errors->items as $item) {
                $html .= sprintf('<li><code>%s %s %s</code> %s</li>', $e($item->timestamp), $e($item->method), $e($item->url), $e($item->message));
            }
            $html .= '</ul>';
        }
        $html .= sprintf('<p style="color:#666;font-size:13px">%s<br>%s · %s · %s · %s</p>', $e($browser->user_agent), $e($browser->page_url),
            $e($browser->screen), $e($browser->timezone), $e($browser->language));
        $html .= sprintf('<p><a href="%s">Ver el reporte en el panel</a> · Respondé este mail para contestarle al cliente.</p></div>', $e($reportUrl));

        $subject = sprintf('%s %s · %s: %s', $report->urgency === 'blocking' ? '[URGENTE]' : '[Reporte]', $report->client->name,
            $report->project->name, Text::oneLine($report->description, 60));

        return new self($subject, $html, $report->user_email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject, replyTo: [new Address($this->clientEmail)]);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body);
    }
}
