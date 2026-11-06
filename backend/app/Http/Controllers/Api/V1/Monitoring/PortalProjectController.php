<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Clients\Models\Client;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Actions\SetProjectFolders;
use App\Domain\StatusPage\Actions\SetProjectLogo;
use App\Domain\StatusPage\Actions\UpdateStatusProject;
use App\Domain\StatusPage\Queries\ProjectAdmin;
use App\Domain\StatusPage\Queries\ProjectLogo;
use App\Domain\StatusPage\Queries\ResponseTimes;
use App\Domain\StatusPage\Queries\StatusPage;
use App\Domain\StatusPage\Queries\Suggestions;
use App\Domain\StatusPage\Queries\Vulnerabilities;
use App\Domain\StatusPage\Status\Uptime;
use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\PortalProjectRequest;
use App\Http\Responses\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * monitoreo /api/portal-admin/projects (RF-13.06): the team's side of the client projects. They are
 * newDesk's projects (ADR-0002 §6): creating and deleting go through RF-03's actions.
 */
class PortalProjectController extends Controller
{
    private const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    private const LOGO_MAX_BYTES = 256 * 1024;

    /** Every project of every client, with its owner and logo (D-048). */
    public function index(ProjectAdmin $admin): JsonResponse
    {
        return ApiResponse::ok($admin->rows(Project::query()->with('client')->orderBy('name')->orderBy('id')->get()));
    }

    public function store(PortalProjectRequest $request, Client $client, SaveProject $save, ProjectAdmin $admin): JsonResponse
    {
        $project = $save([
            'client_id' => $client->id,
            'name' => $request->validated('name'),
            'code_prefix' => $request->validated('code_prefix'),
            'slug' => $request->validated('slug'),
            'leader_user_id' => $request->validated('lead_user_id'),
        ], $request->user());

        return ApiResponse::ok($admin->show($project), 'Proyecto creado.', 201);
    }

    public function show(Project $project, ProjectAdmin $admin): JsonResponse
    {
        return ApiResponse::ok($admin->show($project));
    }

    public function update(PortalProjectRequest $request, Project $project, UpdateStatusProject $update, ProjectAdmin $admin): JsonResponse
    {
        return ApiResponse::ok($admin->show($update($project, $request->updateInput($project), $request->user())), 'Proyecto actualizado.');
    }

    public function destroy(Request $request, Project $project, DeleteProject $delete): JsonResponse
    {
        $delete($project, $request->user());

        return ApiResponse::ok(message: 'Proyecto eliminado.');
    }

    /** The team's preview is the client's page. */
    public function preview(Project $project, StatusPage $page): JsonResponse
    {
        return ApiResponse::ok($page->view($project, ProjectLogo::url($project, forClient: false)));
    }

    public function logo(Project $project): Response
    {
        return ProjectLogo::response($project);
    }

    /** The declared type must match the bytes: no SVG or HTML passes as an image. */
    public function updateLogo(Request $request, Project $project, SetProjectLogo $setLogo, ProjectAdmin $admin): JsonResponse
    {
        $type = (string) $request->input('content_type');
        $image = base64_decode((string) $request->input('data_base64'), true);
        if (! is_string($image) || $image === '' || ! in_array($type, self::LOGO_TYPES, true)
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($image) !== $type) {
            throw ValidationException::withMessages(['logo' => 'El logo tiene que ser PNG, JPG o WebP.']);
        }
        if (strlen($image) > self::LOGO_MAX_BYTES) {
            throw ValidationException::withMessages(['logo' => 'El logo no puede pasar de 256 KB.']);
        }

        return ApiResponse::ok($admin->show($setLogo($project, $image, $type, $request->user())), 'Logo actualizado.');
    }

    public function destroyLogo(Request $request, Project $project, SetProjectLogo $setLogo, ProjectAdmin $admin): JsonResponse
    {
        return ApiResponse::ok($admin->show($setLogo($project, null, null, $request->user())), 'Logo eliminado.');
    }

    public function responseTimes(Request $request, Project $project, ResponseTimes $times): JsonResponse
    {
        return ApiResponse::ok($times($project, self::range($request)));
    }

    /** The last 90 days, with who wrote each note. */
    public function incidents(Project $project): JsonResponse
    {
        $since = CarbonImmutable::now()->utc()->subDays(Uptime::DAYS);

        return ApiResponse::ok(StatusPage::incidents(StatusPage::components($project), $since, 100, forTeam: true));
    }

    public function suggestions(Project $project, Suggestions $suggestions): JsonResponse
    {
        return ApiResponse::ok($suggestions($project));
    }

    public function vulnerabilities(Project $project): JsonResponse
    {
        return ApiResponse::ok(Vulnerabilities::load()->forProject($project));
    }

    public function folders(Request $request, Project $project, SetProjectFolders $setFolders, ProjectAdmin $admin): JsonResponse
    {
        $folders = $request->validate([
            'folders' => ['present', 'nullable', 'array'],
            'folders.*.server_id' => ['required', 'integer'],
            'folders.*.path' => ['required', 'string', 'max:512'],
        ])['folders'] ?? [];
        $setFolders($project, array_map(fn (array $folder) => ['server_id' => (int) $folder['server_id'], 'path' => $folder['path']], $folders), $request->user());

        return ApiResponse::ok($admin->show($project), 'Carpetas guardadas.');
    }

    /** ?range= of the response-time chart (default 24h); the client's endpoint reads it too. */
    public static function range(Request $request): string
    {
        $request->validate(['range' => ['nullable', Rule::in(array_keys(ResponseTimes::RANGES))]], ['range' => 'Usá 24h, 7d o 30d.']);

        return $request->query('range') ?: '24h';
    }
}
