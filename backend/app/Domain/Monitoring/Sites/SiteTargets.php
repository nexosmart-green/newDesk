<?php

namespace App\Domain\Monitoring\Sites;

/**
 * The public names of a server's vhosts (monitoreo sitecheck.Targets/SiteNames): no
 * redirect-only names, wildcards, IPs, catch-alls, ports or credentials.
 */
final class SiteTargets
{
    /**
     * Every primary public name of a `sites` section, sorted and without repeats.
     *
     * @return list<string>
     */
    public static function fromSites(mixed $sites): array
    {
        $sites = json_decode(json_encode($sites), true);
        $names = [];
        foreach (is_array($sites) ? ($sites['items'] ?? []) : [] as $site) {
            foreach (self::siteNames((array) $site) as $name) {
                $names[$name] = true;
            }
        }
        $names = array_keys($names);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * The primary public names of one vhost, lowercased.
     *
     * @param  array<string, mixed>  $site
     * @return list<string>
     */
    public static function siteNames(array $site): array
    {
        $redirects = array_flip(array_map(fn ($name) => strtolower((string) $name), $site['redirects'] ?? []));
        $names = [];
        foreach ($site['server_names'] ?? [] as $name) {
            $name = strtolower(trim((string) $name));
            if (self::isPublicName($name) && ! isset($redirects[$name])) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** The URL https://<name>/ must reach exactly this host: "a@127.0.0.1" or "host:3306" would not (D-085). */
    public static function isPublicName(string $name): bool
    {
        if ($name === '' || $name === '_' || $name === 'localhost' || strpbrk($name, '*~$ /%\\@:?#[]') !== false
            || ! str_contains($name, '.') || filter_var($name, FILTER_VALIDATE_IP) !== false) {
            return false;
        }
        $url = parse_url("https://{$name}/");

        return $url !== false && ! isset($url['user']) && ! isset($url['port']) && ($url['host'] ?? null) === $name;
    }
}
