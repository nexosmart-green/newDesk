<?php

namespace App\Mcp\Tools;

use App\Domain\Assistant\Actions\GenerateAssistantReport;
use App\Domain\Assistant\Enums\ReportKind;
use App\Domain\Assistant\Queries\AssistantReportData;
use App\Domain\Assistant\Support\AssistantTurn;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Support\ReportPeriod;
use App\Infrastructure\Reports\PdfReportWriter;
use App\Infrastructure\Reports\XlsxReportWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('export_report')]
#[Description('Genera un reporte descargable en Excel (xlsx) o PDF: horas por proyecto, cliente o persona en un período, bolsas por agotarse o tareas vencidas. Queda adjunto a la respuesta del asistente para que la persona lo descargue. Solo dentro del Asistente de newDesk. No cambia datos.')]
#[IsReadOnly]
class ExportReportTool extends NewDeskTool
{
    /** Enough for "en Excel y en PDF" of a few reports; more is a loop, not a request. */
    private const FILES_PER_TURN = 4;

    public function __construct(
        private readonly AssistantReportData $data,
        private readonly GenerateAssistantReport $generate,
        private readonly XlsxReportWriter $xlsx,
        private readonly PdfReportWriter $pdf,
    ) {}

    protected function run(Request $request, User $user): Response
    {
        throw new BusinessRuleViolation('Los reportes en archivo se piden desde el Asistente de newDesk (web o Discord).', 'assistant_only');
    }

    protected function perform(Request $request, User $user, ?AssistantTurn $turn): Response
    {
        if ($turn === null || ! $turn->active()) {
            return $this->run($request, $user);
        }

        $input = $request->validate([
            'kind' => ['required', Rule::enum(ReportKind::class)],
            'format' => 'required|in:xlsx,pdf',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        if ($turn->answer()?->files()->count() >= self::FILES_PER_TURN) {
            throw new BusinessRuleViolation('Ya generé '.self::FILES_PER_TURN.' archivos en esta respuesta: pedí los demás en un mensaje nuevo.', 'assistant_files_per_turn');
        }
        $kind = ReportKind::from($input['kind']);
        $this->ensureModule($user, $kind->module());
        if ($kind->isHours()) {
            Gate::forUser($user)->authorize(Permission::ProjectsManage->value);
        }

        $table = ($this->data)($kind, $user, ReportPeriod::fromInput($user, $input['from'] ?? null, $input['to'] ?? null));
        $file = ($this->generate)($user, $table, $input['format'] === 'pdf' ? $this->pdf : $this->xlsx, $turn->answer());

        return $this->json([
            'file' => $file->name,
            'rows' => count($table->rows),
            'note' => 'El archivo ya quedó adjunto a tu respuesta con un botón de descarga: avisale a la persona, no pegues un link.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(array_column(ReportKind::cases(), 'value'))->required(),
            'format' => $schema->string()->enum(['xlsx', 'pdf'])->required(),
            'from' => $schema->string()->description('YYYY-MM-DD, solo para horas (default: el 1 del mes actual).'),
            'to' => $schema->string()->description('YYYY-MM-DD, solo para horas (default: hoy).'),
        ];
    }
}
