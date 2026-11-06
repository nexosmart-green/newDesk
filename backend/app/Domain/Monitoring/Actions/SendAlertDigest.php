<?php

namespace App\Domain\Monitoring\Actions;

use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Discord\DiscordMessage;
use App\Domain\Monitoring\Discord\Notifier;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\StatusPage\Notifications\StatusPageMail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * The twice-daily alert digest (monitoreo alerts.Digest, D-046, D-059, D-061): what opened or
 * resolved since the previous cut plus what is still open, mailed to DIGEST_EMAIL_TO and posted to
 * #resumen. Nothing goes out when nothing happened and nothing is open, nor while notices are off
 * (CA-13.20).
 *
 * ponytail: one installation-wide digest, like monitoreo's: DIGEST_EMAIL_TO and #resumen are global.
 * Split it per company when a second company has its own recipients.
 */
final class SendAlertDigest
{
    public const TIMEZONE = 'America/Argentina/Buenos_Aires';

    /** Local hours it goes out (D-046). */
    public const HOURS = [9, 18];

    public function __construct(private readonly Notifier $notifier) {}

    /** The latest digest time at or before $time (monitoreo LastDigestCut). */
    public static function lastCut(CarbonImmutable $time): CarbonImmutable
    {
        $day = $time->setTimezone(self::TIMEZONE)->startOfDay();
        for ($offset = 0; ; $offset--) {
            foreach (array_reverse(self::HOURS) as $hour) {
                $cut = $day->addDays($offset)->setTime($hour, 0);
                if ($cut->lte($time)) {
                    return $cut;
                }
            }
        }
    }

    /** At a cut (the scheduler's 9 and 18 h): the digest since the previous one. */
    public function __invoke(): void
    {
        $to = self::lastCut(CarbonImmutable::now());
        $this->send(self::lastCut($to->subSecond()), $to);
    }

    /** The digest of [$from, $to). */
    public function send(CarbonImmutable $from, CarbonImmutable $to): void
    {
        if (! config('newdesk.monitoring.notify')) {
            return;
        }
        $window = fn (string $column) => fn ($query) => $query->where($column, '>=', $from->utc())->where($column, '<', $to->utc());
        $alerts = Alert::query()->withoutGlobalScopes()->join('monitoring_servers', 'monitoring_servers.id', '=', 'monitoring_alerts.server_id')
            ->where(fn ($query) => $query->where($window('monitoring_alerts.first_seen_at'))->orWhere($window('monitoring_alerts.resolved_at'))
                ->orWhere('monitoring_alerts.state', Alert::FIRING))
            ->orderBy('monitoring_servers.name')->orderBy('monitoring_alerts.first_seen_at')->orderBy('monitoring_alerts.id')
            ->get(['monitoring_alerts.*', 'monitoring_servers.name as server_name']);

        $open = [];
        $groups = [];
        $events = 0;
        $inWindow = fn (?CarbonImmutable $time) => $time !== null && $time->gte($from) && $time->lt($to);
        foreach ($alerts as $alert) {
            if ($alert->state === Alert::FIRING) {
                $open[] = $alert;
            }
            if (! $inWindow($alert->first_seen_at) && ! $inWindow($alert->resolved_at)) {
                continue;
            }
            $events++;
            $key = "{$alert->server_id}|{$alert->rule}|{$alert->target}";
            $groups[$key] ??= ['server' => $alert->server_name, 'first' => $alert->first_seen_at, 'count' => 0, 'mailed' => false];
            // Rows come ordered by first_seen_at: the last one is the current state.
            $groups[$key]['count']++;
            $groups[$key]['message'] = $alert->message;
            $groups[$key]['state'] = $alert->state;
            $groups[$key]['last'] = $alert->last_seen_at;
            $groups[$key]['mailed'] = $groups[$key]['mailed'] || $alert->last_notified_at !== null;
        }
        if ($events === 0 && $open === []) {
            return;
        }

        $local = fn (CarbonImmutable $time) => $time->setTimezone(self::TIMEZONE)->format('d/m H:i');
        $openLines = [];
        foreach ($open as $alert) {
            $severity = $alert->severity === AlertRules::CRITICAL ? 'crítico' : 'advertencia';
            $openLines[$alert->server_name][] = "{$alert->message} ({$severity}, desde {$local($alert->first_seen_at)})";
        }
        $eventLines = [];
        foreach ($groups as $group) {
            $line = sprintf('%s: %d %s, %s a %s, %s', $group['message'], $group['count'], $group['count'] === 1 ? 'vez' : 'veces',
                $local($group['first']), $local($group['last']), $group['state'] === Alert::FIRING ? 'abierta' : 'resuelta');
            $eventLines[$group['server']][] = $line.($group['mailed'] ? ' (avisada al instante)' : '');
        }

        $html = '';
        $chat = [];
        foreach (['Sigue abierto' => $openLines, 'En el período' => $eventLines] as $title => $byServer) {
            if ($byServer === []) {
                continue;
            }
            $html .= '<h3>'.e($title).'</h3>';
            $chat[] = "__{$title}__";
            foreach ($byServer as $server => $lines) {
                $html .= '<p><strong>'.e((string) $server).'</strong></p><ul>';
                $chat[] = '**'.DiscordMessage::escape((string) $server).'**';
                foreach ($lines as $line) {
                    $html .= '<li>'.e($line).'</li>';
                    $chat[] = '• '.DiscordMessage::escape($line);
                }
                $html .= '</ul>';
            }
        }

        [$fromHour, $toHour] = [$from->setTimezone(self::TIMEZONE)->hour, $to->setTimezone(self::TIMEZONE)->hour];
        $subject = sprintf('[RESUMEN] %d a %d h: %d eventos, %d abiertas', $fromHour, $toHour, $events, count($open));
        $link = Notifier::monitoringUrl('/alerts');
        $this->notifier->summary(self::chat(sprintf('📋 **Resumen de %d a %d h** · %d eventos, %d abiertas', $fromHour, $toHour, $events, count($open)), $chat, $link));

        $recipients = array_filter(array_map('trim', explode(',', (string) (config('newdesk.monitoring.digest_email_to') ?: config('newdesk.monitoring.alert_email')))));
        if ($recipients !== []) {
            Mail::to($recipients)->queue(new StatusPageMail($subject, sprintf('<div style="font-family:system-ui,sans-serif;font-size:14px;color:#111">'
                ."\n".'<p><strong>%s</strong></p>%s<p><a href="%s">Ver en el panel</a></p></div>', e($subject), $html, e($link))));
        }
    }

    /**
     * The digest in one Discord message: the lines that do not fit become "…y N líneas más en el
     * panel", and the link always stays.
     *
     * @param  list<string>  $lines
     */
    public static function chat(string $headline, array $lines, string $link): string
    {
        $tail = "\n<{$link}>";
        $out = $headline;
        foreach ($lines as $i => $line) {
            $more = sprintf("\n…y %d líneas más en el panel", count($lines) - $i);
            if (mb_strlen($out."\n".$line.$more.$tail) > DiscordMessage::MAX_CONTENT) {
                $out .= $more;
                break;
            }
            $out .= "\n".$line;
        }

        return $out.$tail;
    }
}
