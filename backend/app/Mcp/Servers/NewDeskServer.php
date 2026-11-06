<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('newDesk')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
newDesk es la herramienta de proyectos, tareas y horas de la empresa. Actuás en nombre del usuario dueño del token,
con exactamente los permisos de su rol actual.
- Las tareas se identifican por código (ej. "bhn-60") y los proyectos por prefijo (ej. "bhn").
- Para detener el cronómetro (stop_timer) o cambiar a otra tarea con uno corriendo (start_timer), el comentario es
  OBLIGATORIO: describí concretamente qué se hizo (mínimo 10 caracteres). Ese texto lo lee el cliente.
- Si whoami muestra comentarios pendientes, completalos con complete_pending_comment antes de iniciar otro cronómetro.
- Si un proyecto no tiene horas disponibles no se puede cronometrar: avisale al usuario que pida una ampliación.
TXT)]
class NewDeskServer extends Server
{
    /** All R1 tools in one tools/list page (clients that ignore nextCursor still see every tool). */
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        Tools\WhoAmI::class,
        Tools\ListMyTasks::class,
        Tools\ListProjects::class,
        Tools\GetProject::class,
        Tools\GetTask::class,
        Tools\CreateTaskTool::class,
        Tools\AssignTaskTool::class,
        Tools\UpdateTaskStatus::class,
        Tools\CommentTask::class,
        Tools\StartTimerTool::class,
        Tools\StopTimerTool::class,
        Tools\GetActiveTimer::class,
        Tools\CompletePendingCommentTool::class,
        Tools\LogTime::class,
        Tools\MyTimesheet::class,
        Tools\MyShifts::class,
        Tools\AllocateHoursTool::class,
        Tools\ProjectHoursReport::class,
    ];
}
