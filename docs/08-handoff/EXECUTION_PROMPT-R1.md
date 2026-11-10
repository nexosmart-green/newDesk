# EXECUTION PROMPT — newDesk R1 (para la sesión implementadora)

> Working dir: `C:\NexoSmart\Projects\newDesk` · Repo: https://github.com/nexosmart-green/newDesk
> Todos los paths de este documento son **absolutos** para que las tools no fallen sin `cd` previo.

## Rol

Sos la sesión **implementadora** de newDesk. La sesión de análisis (PO/analista/QA) mantiene `docs/`. Vos construís R1 siguiendo esos documentos. **No inventes alcance**: si algo no está en un RF con criterios de aceptación, o un CA es ambiguo, anotalo en `C:\NexoSmart\Projects\newDesk\docs\03-gaps\preguntas-abiertas.md` (sección Pendientes, ID siguiente libre) y seguí con otra cosa.

## Leer primero (en este orden)

1. `C:\NexoSmart\Projects\newDesk\CLAUDE.md`
2. `C:\NexoSmart\Projects\newDesk\docs\00-vision\vision-y-alcance.md`
3. `C:\NexoSmart\Projects\newDesk\docs\02-requerimientos\README.md` (índice + matriz de permisos)
4. `C:\NexoSmart\Projects\newDesk\docs\02-requerimientos\RF-05-cronometro-y-horas.md` ⭐ (el corazón; reglas R-1..R-5)
5. `C:\NexoSmart\Projects\newDesk\docs\02-requerimientos\RF-12-mcp-claude-code.md`
6. `C:\NexoSmart\Projects\newDesk\docs\04-arquitectura\arquitectura.md` y `C:\NexoSmart\Projects\newDesk\docs\04-arquitectura\modelo-de-datos.md`
7. `C:\NexoSmart\Projects\newDesk\docs\05-qa\estrategia-de-pruebas.md`
8. `C:\NexoSmart\Projects\newDesk\docs\07-backlog\backlog.md` (épicas y orden)
9. El resto de `C:\NexoSmart\Projects\newDesk\docs\02-requerimientos\RF-*.md` a medida que llegues a cada épica.

Globales del usuario: `C:\Users\GREEN\.claude\knowledge\backend-base.md`, `C:\Users\GREEN\.claude\knowledge\frontend-base.md`.

## Stack (no negociable sin ADR)

- `C:\NexoSmart\Projects\newDesk\backend` → Laravel 12, PHP 8.3, MySQL 8, Redis, Sanctum, Reverb, `laravel/mcp`, Pest. Respuestas `{ data, message, errors }`, API Resources, paginado por defecto.
- `C:\NexoSmart\Projects\newDesk\frontend` → React 19 + Vite + TypeScript strict, Vitest + RTL, Playwright.
- Procesos en Supervisord (`supervisor:sync`), nunca crontab. Mail con Resend por cola, 1/s.
- Ruta raíz de prod devuelve `{"status":"ok"}`; sin headers que filtren el stack.

## Orden de trabajo

| Paso | Épica | Hecho cuando |
|---|---|---|
| 1 | **E0 Fundaciones**: scaffolding back+front, CI (GitHub Actions: tests back+front), `/bug-report setup` (`/health`, `/errors`, canal `errors`, `ERRORS_TOKEN`, `.claude/bug-report.json` gitignored), auth Sanctum SPA + invitación + 2FA admin, `companies` + `company_id` en todo, **roles editables con permisos granulares** (P-02 ✅), policies, RF-11 configuración empresa | login funciona en local; `/health` 200; test de policy 403/404 verde |
| 2 | E1 Personas (RF-01) | CA-01.* verdes |
| 3 | E2 Clientes y proyectos (RF-02, RF-03) | CA-03.* verdes |
| 4 | E3 Tareas (RF-04) | CA-04.01 bajo concurrencia verde |
| 5 | **E4 Horas (RF-05)** — incluye RF-05.16/17 comentario obligatorio y comentario pendiente | QA-01..08 verdes |
| 6 | E6 Notificaciones, E5 Turnos, E7 Reporte | CA respectivos |
| 7 | **E13 MCP (RF-12)** — tokens personales, pantalla "Conexiones de IA", tools R1 | CA-12.* + QA-01..03 vía MCP verdes |
| 8 | E8 Puesta en marcha | checklist del backlog |

## Reglas duras

- **Una sola capa de reglas**: controllers y tools MCP llaman a las mismas actions de dominio (`StartTimer`, `StopTimer`, `CreateTask`, `AllocateHours`…). Nada de reglas en controllers/tools.
- Timer en servidor, UTC, segundos enteros. Un timer activo por usuario (columna generada UNIQUE). Iniciar timer con `SELECT … FOR UPDATE` del proyecto.
- **Detener timer exige comentario (≥10 caracteres)**, web y MCP. Cortes automáticos → `comment_pending`, bloquea el próximo timer hasta completarlo.
- Tokens MCP: Sanctum con prefijo `nd_`, hash, se muestran una vez, permisos evaluados **por request** contra el rol actual.
- Tests contra MySQL real de testing **aislada** (DB propia `newdesk_testing`), nunca la de desarrollo.
- Rama de trabajo R1: `dev_green` (pedido del usuario 2026-11-06). Conventional commits en inglés; pushear siempre.
- Al cerrar cada épica: actualizar `C:\NexoSmart\Projects\newDesk\tasks\todo.md` (review) y marcar en `C:\NexoSmart\Projects\newDesk\docs\07-backlog\backlog.md`.

## Fuera de alcance R1

Asistencia/cerradura (RF-07, spike SP-01 aparte), licencias (RF-08), migración de Worksuite, portal de clientes, OAuth para claude.ai (RF-12.08), abono mensual.
