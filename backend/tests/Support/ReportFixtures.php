<?php

namespace Tests\Support;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Support\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/** Problem reports for tests, as monitoreo's report tests build them (postReport, postMessage, h.loginClient). */
final class ReportFixtures
{
    public const FIELDS = ['description' => 'El botón de pagar queda girando.', 'urgency' => 'annoying'];

    /** A 1×1 PNG (monitoreo tinyPNG). */
    public static function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    /** 1000 bytes that start like a PNG: what the upload quota test sends. */
    public static function kilobytePng(): string
    {
        return "\x89PNG\r\n\x1a\n".str_repeat("\x07", 992);
    }

    public static function upload(string $bytes, string $name = 'captura.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    /** A portal user of the project's client. */
    public static function clientUser(Project $project, string $email): User
    {
        $client = Client::query()->forCompany($project->company_id)->findOrFail($project->client_id);
        $role = $client->company->roles()->where('name', DefaultRoles::CLIENT)->firstOrFail();

        return User::factory()->forRole($role)->create(['client_id' => $client->id, 'email' => $email])->fresh();
    }

    /** Signs in as someone else (the Sanctum guard keeps the previous user otherwise). */
    public static function signIn(User $user): User
    {
        Auth::forgetGuards();
        test()->actingAs($user);

        return $user;
    }

    /** POST /api/v1/portal/projects/{slug}/reports as the portal sends it (multipart). */
    public static function post(string $slug, array $fields = self::FIELDS, ?string $screenshot = null): TestResponse
    {
        return test()->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 (Android 14) Chrome/130'])
            ->post("/api/v1/portal/projects/{$slug}/reports", $screenshot === null ? $fields : [...$fields, 'screenshot' => self::upload($screenshot)]);
    }

    /** A chat message, multipart: body + images (+ internal). $path is the report's API path. */
    public static function message(string $path, string $body, array $images = [], array $fields = []): TestResponse
    {
        $files = array_map(fn (string $bytes) => self::upload($bytes, 'imagen.png'), $images);

        return test()->withHeaders(['Accept' => 'application/json'])->post("{$path}/messages", ['body' => $body, ...$fields, 'images' => $files]);
    }

    /** The frontend's apiUrl(): monitoreo-style API paths ("api/portal/…", "api/portal-admin/…") under /api/v1. */
    public static function url(string $path): string
    {
        return str_starts_with($path, 'api/portal/') ? '/api/v1/'.substr($path, 4) : '/api/v1/monitoring/'.substr($path, 4);
    }

    /** A report row stored as it is, outside the API (monitoreo's tests call Store.CreateReport). */
    public static function stored(Project $project, array $attributes = []): Report
    {
        return Report::query()->create([
            'company_id' => $project->company_id, 'project_id' => $project->id, 'client_id' => $project->client_id, 'user_email' => 'dueno@playinn.com',
            'urgency' => 'annoying', 'description' => 'antes', 'live_check' => (object) [], 'open_issues' => [], 'browser' => (object) [], 'ip' => '',
            ...$attributes,
        ]);
    }
}
