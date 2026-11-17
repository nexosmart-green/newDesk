<?php

namespace App\Domain\StatusPage\Status;

use App\Domain\StatusPage\Models\Component;
use Carbon\CarbonImmutable;

/**
 * The client sees its project in its own words (D-045): the web, the app and the CMS, only the
 * ones it has. Technical components (API, workers) affect one or more parts; backups none.
 */
final class Parts
{
    public const WEB = 'web';

    public const APP = 'app';

    public const CMS = 'cms';

    public const ORDER = [self::WEB, self::APP, self::CMS];

    public const LABELS = [self::WEB => 'Web', self::APP => 'App', self::CMS => 'CMS'];

    /**
     * The known parts once, in web, app, cms order; null when one is unknown.
     *
     * @param  list<string>  $parts
     * @return list<string>|null
     */
    public static function normalize(array $parts): ?array
    {
        if (array_diff($parts, self::ORDER) !== []) {
            return null;
        }

        return array_values(array_intersect(self::ORDER, $parts));
    }

    /** "App", "App y CMS", "Web, App y CMS". @param  list<string>  $keys */
    public static function label(array $keys): string
    {
        $labels = array_values(array_map(fn (string $key) => self::LABELS[$key], array_intersect(self::ORDER, $keys)));
        if (count($labels) < 2) {
            return $labels[0] ?? '';
        }

        return implode(', ', array_slice($labels, 0, -1)).' y '.end($labels);
    }

    /**
     * The parts a project's components already cover.
     *
     * @param  iterable<Component>  $components
     * @return list<string>
     */
    public static function present(iterable $components): array
    {
        $covered = [];
        foreach ($components as $component) {
            $covered = [...$covered, ...$component->parts];
        }

        return array_values(array_intersect(self::ORDER, $covered));
    }

    /**
     * Each part the project has, in order: the worst of the components that affect it, with the
     * phrase and since of the first one in that state.
     *
     * @param  iterable<Component>  $components
     * @return list<array{key: string, label: string, status: string, phrase: string, since: CarbonImmutable|null, components: list<Component>}>
     */
    public static function states(iterable $components): array
    {
        $parts = [];
        foreach (self::ORDER as $key) {
            $members = array_values(array_filter([...$components], fn (Component $component) => in_array($key, $component->parts, true)));
            if ($members === []) {
                continue;
            }
            $status = ComponentState::worst(...array_map(fn (Component $member) => $member->status, $members));
            $first = array_values(array_filter($members, fn (Component $member) => $member->status === $status))[0];
            $parts[] = [
                'key' => $key, 'label' => self::LABELS[$key], 'status' => $status,
                'phrase' => self::phrase($first), 'since' => $first->status_since, 'components' => $members,
            ];
        }

        return $parts;
    }

    /** Never an empty phrase for a client. */
    public static function phrase(Component $component): string
    {
        return $component->phrase === '' || $component->status === ComponentState::UNKNOWN ? Result::PHRASE_NO_DATA : $component->phrase;
    }

    /**
     * What a component affects when the team does not say: a web URL the part its host names, an
     * API or a process every part the project already has (the web when none), a backup none.
     *
     * @param  iterable<Component>  $existing
     * @return list<string>
     */
    public static function default(string $kind, ?string $url, iterable $existing): array
    {
        if ($kind === Component::TIMER) {
            return [];
        }
        if ($kind === Component::HTTP) {
            return [self::place((string) preg_replace('#^https?://#', '', (string) $url), '')];
        }

        return self::present($existing) ?: [self::WEB];
    }

    /** A web URL the way the client knows it: the CMS, the app or the web, from the host or else the folder name. */
    public static function place(string $host, string $folder): string
    {
        foreach ([strtolower(explode('.', $host, 2)[0]), strtolower($folder)] as $hint) {
            if (str_contains($hint, 'cms') || str_contains($hint, 'admin') || str_contains($hint, 'panel')) {
                return self::CMS;
            }
            if (str_starts_with($hint, 'app') || str_ends_with($hint, 'app') || str_contains($hint, '_app') || str_contains($hint, '-app')) {
                return self::APP;
            }
        }

        return self::WEB;
    }
}
