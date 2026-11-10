<?php

namespace Tests\Support;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\Period;
use App\Domain\StatusPage\Status\ComponentState;
use Carbon\CarbonImmutable;

/** Status page rows for tests, as monitoreo's tests build them through its store. */
final class StatusPageFixtures
{
    /** An unsaved component (store.PortalComponent in monitoreo's unit tests). */
    public static function component(array $attributes): Component
    {
        return (new Component)->forceFill(['kind' => Component::HTTP, 'label' => '', 'parts' => [], 'status' => ComponentState::UNKNOWN, 'phrase' => '', ...$attributes]);
    }

    public static function period(int $componentId, string $status, CarbonImmutable $start, ?CarbonImmutable $end = null): Period
    {
        return (new Period)->forceFill(['component_id' => $componentId, 'status' => $status, 'started_at' => $start, 'ended_at' => $end]);
    }

    public static function project(User $admin, string $slug = 'playinn', string $client = 'Playinn'): Project
    {
        $owner = Client::query()->forCompany($admin->company_id)->firstOrCreate(['company_id' => $admin->company_id, 'legal_name' => $client]);

        return Project::factory()->create(['company_id' => $admin->company_id, 'client_id' => $owner->id, 'name' => ucfirst($slug),
            'slug' => $slug, 'code_prefix' => substr(str_replace('-', '', $slug), 0, 10)]);
    }

    public static function saved(Project $project, array $attributes): Component
    {
        return Component::query()->create(['company_id' => $project->company_id, 'project_id' => $project->id, 'kind' => Component::HTTP,
            'label' => 'Sitio web', 'parts' => ['web'], ...$attributes]);
    }

    public static function server(User $admin, string $name = 'V5', array $attributes = []): Server
    {
        return tap((new Server)->forceFill(['company_id' => $admin->company_id, 'name' => $name, 'ip' => '203.0.113.5', 'agent_port' => 9100,
            'token' => FakeAgentGateway::TOKEN, 'tls_fingerprint' => FakeAgentGateway::FINGERPRINT, ...$attributes]))->save();
    }

    /** The latest answer of one section, collected now. @param  array<string, mixed>|string  $data */
    public static function snapshot(Server $server, string $section, array|string $data): void
    {
        ServerSnapshot::query()->withoutGlobalScopes()->updateOrInsert(['server_id' => $server->id, 'section' => $section], [
            'company_id' => $server->company_id, 'collected_at' => now()->utc(), 'fetched_at' => now()->utc(),
            'data' => is_string($data) ? $data : json_encode($data),
        ]);
    }

    /** A section from monitoreo's testdata (the real agents' answers). */
    public static function fixture(string $server, string $section): string
    {
        return (string) file_get_contents(base_path("tests/Fixtures/monitoreo/servers/{$server}/{$section}.json"));
    }
}
