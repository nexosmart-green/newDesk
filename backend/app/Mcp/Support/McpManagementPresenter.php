<?php

namespace App\Mcp\Support;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Models\HourExtensionRequest;

/**
 * Compact shapes of the management tools (RF-13.07): clients, projects with their client and leader, people and
 * extension requests. People show only what the company directory shows to anyone: never rates or personal data.
 */
final class McpManagementPresenter
{
    /** @param  list<int>  $userIds */
    public static function names(int $companyId, array $userIds): string
    {
        return User::query()->where('company_id', $companyId)->whereKey($userIds)->get()
            ->map(fn (User $person) => $person->fullName())->implode(', ');
    }

    /** @return array<string, mixed> */
    public static function client(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->displayName(),
            'legal_name' => $client->legal_name,
            'tax_id' => $client->tax_id,
            'contact_name' => $client->contact_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'status' => $client->status->value,
        ];
    }

    /** McpPresenter::project plus who it is for and who leads it. @return array<string, mixed> */
    public static function project(Project $project): array
    {
        $project->loadMissing(['client', 'leader']);

        return [
            ...McpPresenter::project($project),
            'client' => $project->client?->displayName(),
            'leader' => $project->leader?->fullName(),
        ];
    }

    /** @return array<string, mixed> */
    public static function person(User $person): array
    {
        $person->loadMissing(['profile.jobTitle', 'profile.area']);

        return [
            'id' => $person->id,
            'name' => $person->fullName(),
            'email' => $person->email,
            'status' => $person->status->value,
            'job_title' => $person->profile?->jobTitle?->name,
            'area' => $person->profile?->area?->name,
        ];
    }

    /** @return array<string, mixed> */
    public static function extensionRequest(HourExtensionRequest $request): array
    {
        $request->loadMissing(['project', 'requester']);

        return [
            'id' => $request->id,
            'status' => $request->status->value,
            'project' => $request->project->code_prefix,
            'project_name' => $request->project->name,
            'requested_by' => $request->requester->fullName(),
            'message' => $request->message,
            'requested_at' => $request->created_at?->toIso8601String(),
            'project_hours' => McpPresenter::project($request->project)['hours'],
        ];
    }
}
