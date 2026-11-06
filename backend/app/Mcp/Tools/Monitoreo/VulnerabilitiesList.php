<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\StatusPage\Queries\ProjectAdmin;
use App\Domain\StatusPage\Queries\ProjectLookup;
use App\Domain\StatusPage\Queries\Vulnerabilities;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('vulnerabilities_list')]
#[Description('Vulnerabilidades de dependencias (npm audit y composer audit) del último security-scan.sh de cada server, por proyecto y carpeta: paquete, severidad, título, CVE, link y si npm tiene fix. Solo lista proyectos con hallazgos o auditorías que fallaron. Por defecto solo los tuyos.')]
#[IsReadOnly]
final class VulnerabilitiesList extends MonitoreoTool
{
    private const ORDER = ['critical' => 0, 'high' => 1, 'moderate' => 2, 'low' => 3];

    protected function properties(): array
    {
        return [
            'project' => self::PROJECT,
            'severity' => ['type' => 'string', 'enum' => array_keys(self::ORDER), 'description' => 'Severidad mínima (low por defecto = todas).'],
            'all' => ['type' => 'boolean', 'description' => 'true trae los proyectos de todos y también las carpetas que no están vinculadas a ningún proyecto.'],
        ];
    }

    public function answer(Request $request, ProjectLookup $lookup): Response
    {
        $severity = (string) $request->get('severity');
        $minimum = $severity === '' ? self::ORDER['low'] : (self::ORDER[$severity] ?? null);
        if ($minimum === null) {
            throw new BusinessRuleViolation('severity tiene que ser critical, high, moderate o low.', 'invalid_severity');
        }
        $all = (bool) $request->get('all');
        $named = trim((string) $request->get('project'));
        $projects = $named !== '' ? new Collection([$lookup->find($named)]) : $lookup->scoped(self::user(), $all);
        $vulnerabilities = Vulnerabilities::load();
        $developers = ProjectAdmin::developers($projects->modelKeys());
        $dirs = fn (array $dirs) => self::dirs($dirs, $minimum);

        $out = [];
        foreach ($projects as $project) {
            /** @var Project $project */
            $found = $dirs($vulnerabilities->byProject[$project->id] ?? []);
            if ($found === [] && $projects->count() > 1) {
                continue;
            }
            $out[] = ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug, 'client' => $project->client->name,
                'developers' => $developers[$project->id] ?? [], 'counts' => $vulnerabilities->counts($project->id), 'dirs' => $found];
        }
        $result = ['projects' => $out, 'total' => count($out), 'scope' => $named !== '' ? 'project' : ($all ? 'all' : 'mine')];
        if ($all && $named === '') {
            $result['unassigned_dirs'] = $dirs($vulnerabilities->unassigned);
        }
        if ($vulnerabilities->scannedAt === []) {
            $result['note'] = 'Ningún server mandó todavía el resultado de security-scan.sh.';
        }

        return self::json($result);
    }

    /**
     * The folders with findings at or above the minimum severity, or whose audit failed.
     *
     * @param  list<array<string, mixed>>  $dirs
     * @return list<array<string, mixed>>
     */
    private static function dirs(array $dirs, int $minimum): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $findings = array_values(array_filter($dir['findings'] ?? [], fn (array $finding) => (self::ORDER[$finding['severity'] ?? ''] ?? 0) <= $minimum));
            $error = (string) ($dir['error'] ?? '');
            if ($findings === [] && $error === '') {
                continue;
            }
            $out[] = ['server' => $dir['server_name'], 'path' => $dir['path'] ?? '', 'manager' => $dir['manager'] ?? '',
                ...(! empty($dir['report_only']) ? ['report_only' => true] : []), ...($error !== '' ? ['error' => $error] : []),
                'scanned_at' => $dir['scanned_at'], 'counts' => $dir['counts'] ?? null, 'findings' => $findings];
        }

        return $out;
    }
}
