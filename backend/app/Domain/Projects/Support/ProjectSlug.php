<?php

namespace App\Domain\Projects\Support;

use App\Domain\Projects\Models\Project;
use Illuminate\Validation\ValidationException;

/**
 * The project's identifier in the client's portal URL (RF-13.07), taken from its name as monitoreo does.
 */
final class ProjectSlug
{
    /** Slugs the portal SPA uses for its own routes (/portal/login): a project there would be shadowed. */
    public const RESERVED = ['login'];

    public const TAKEN = 'Ese identificador ya está en uso.';

    /** "Café Nueva Córdoba" → cafe-nueva-cordoba (monitoreo portal.Slugify). */
    public static function slugify(string $name): string
    {
        $folded = strtr(mb_strtolower($name), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim(substr(trim((string) preg_replace('/[^a-z0-9]+/u', '-', $folded), '-'), 0, 50), '-') ?: 'proyecto';
    }

    /** The name's slug; a taken one gets a number up to -20. */
    public static function available(string $name, int $companyId): string
    {
        $base = self::slugify($name);

        foreach ([$base, ...array_map(fn (int $n) => "{$base}-{$n}", range(2, 20))] as $candidate) {
            if (! in_array($candidate, self::RESERVED, true)
                && ! Project::query()->withTrashed()->forCompany($companyId)->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages(['slug' => self::TAKEN]);
    }
}
