# Convenciones de código

Regla del PO (2026-11-06): **clean code, SOLID, patrones de diseño, código apto para IA, arquitectura ordenada.**
"Apto para IA" = un agente (o una persona nueva) encuentra dónde va cada cosa sin leer todo el repo:
nombres que dicen qué hacen, un lugar por responsabilidad, convenciones repetidas igual en todos los módulos.

## Backend (`backend/`, Laravel 12)

### Capas

```
HTTP / MCP (adaptadores delgados)       app/Http/Controllers/Api/V1/<Modulo>/  ·  app/Mcp/Tools/
   │  validan (FormRequest), autorizan (Policy), llaman UNA action o query, devuelven un Resource
   ▼
Dominio                                  app/Domain/<Modulo>/
   Actions/      un caso de uso por clase, invocable (__invoke). Transacción + reglas + RecordActivity
   Queries/      lecturas (listados, reportes, tableros). Sin escrituras. Devuelven paginator / array / colección
   Support/      piezas que comparten varias actions/queries y NO son un caso de uso: guards (EnsureX),
                 resolutores (ResolveX), value objects (HourBag, DateRange), escritores (ShiftCellWriter),
                 límites de entrada compartidos web/MCP (TaskLimits, TimeTrackingLimits)
   Models/       Eloquent: relaciones, casts, scopes. Sin reglas de casos de uso
   Policies/     quién puede (ver/editar/...). 404 en vez de 403 cuando no debe saber que existe (CA-03.03)
   Enums/        estados y catálogos fijos (string-backed). Nunca literales ('active', 'pending') en el código
   Events/       hechos del dominio; otro módulo reacciona sin que el emisor lo conozca
   Listeners/    suscriptores a eventos de otros módulos (ej. TimeTracking corta timers al cerrar una tarea)
   Contracts/    interfaces de integraciones externas; el adaptador vive en app/Infrastructure/
   Notifications/ (Laravel)
   ▼
Shared                                   app/Domain/Shared/
   Concerns/BelongsToCompany   scope + company_id automático
   Activity/RecordActivity     auditoría (único punto de escritura de activity_log); ActivityFeed la lee
   Exceptions/BusinessRuleViolation   regla de negocio rechazada → 422 con mensaje humano + `code` (+ `field`)
   Support/DateRange, Duration  rango de días con tope; segundos → "1h 30m"
```

### Reglas

1. **Una sola capa de reglas.** Controllers y tools MCP nunca contienen reglas; llaman a la misma action
   (`StartTimer`, `CreateTask`, `AllocateHours`…). Si una tool MCP necesita algo que la web no hace, va a una action.
2. **SRP:** una action = un caso de uso. Si el nombre necesita "And", o la clase tiene varios métodos públicos
   (`approve`/`reject`), son varias actions (`ApproveHourExtension`, `RejectHourExtension`). Lo común entre ellas va a
   `Support/` (`PendingExtensionLock`, `TimerCutter`). Lo que no es un caso de uso (guards, resolutores, destinatarios)
   no va en `Actions/`: va a `Support/` o `Queries/`.
3. **Nombres:** acciones `<Verbo><Sustantivo>` (`DeleteHoliday`, `CutTaskTimers`). Alta y edición con el mismo
   formulario = **`Save<X>`** (`SaveClient`, `SaveShiftTemplate`, `SaveHoliday`): recibe `?X $existing` y decide crear o
   actualizar. Queries por lo que devuelven (`ClientList`, `ActivityFeed`, `CalendarFeedLookup`).
4. **OCP/DIP:** dependencias por constructor (inyección del container). Nada de `app(...)`/`auth()` escondidos en
   resources, presenters o modelos: lo que se necesita se pasa (ej. `McpPresenter::task($task, $viewer)`).
   Integraciones externas detrás de una interfaz en `Domain/<Modulo>/Contracts` con adaptador en `app/Infrastructure/`.
5. **Autorización = Policy** (o el Gate del permiso), la misma en web y MCP:
   - **Policy** responde "¿esta persona puede hacer esto con este recurso?" (permiso + pertenencia + empresa).
     Deniega con `Response::deny('mensaje humano', 'code')` (→ 403 con `data.code`) o `denyAsNotFound()` (→ 404).
     Ejemplos: `ProjectPolicy::allocateHours`, `HourExtensionRequestPolicy::approve`, `TimeEntryPolicy::completeComment`.
   - **`BusinessRuleViolation`** responde "¿el estado del dominio permite esto ahora?" (sin horas, ya resuelto,
     fuera de la ventana). Nunca para permisos.
   - Web: si el endpoint tiene FormRequest, autoriza en su `authorize()` (corre **antes** de validar: un ajeno recibe
     404 aunque mande basura, CA-03.03); si no, `Gate::authorize()` en el controller. MCP: `McpLookup` autoriza `view`
     al resolver tarea/proyecto y la tool llama `Gate::forUser($user)->authorize(...)` para lo demás.
6. **Validación:** web = **FormRequest** (`app/Http/Requests/<Modulo>/<Verbo><Sustantivo>Request`), nunca
   `$request->validate()` inline. Los requests exponen helpers tipados (`range()`, `utcRange()`, `filters()`,
   `userIds()`). Límites que también valida MCP salen de constantes compartidas (`TaskLimits::COMMENT_BODY_MAX`,
   `TimeTrackingLimits::ENTRY_COMMENT_MAX`…) y enums con `Rule::enum(X::class)`.
7. **Errores de negocio** con `BusinessRuleViolation(message, ruleCode, field: ...)`. El mensaje es para el usuario
   final y es el mismo en web y MCP. Si la regla es sobre un campo (ej. «Hasta» antes que «Desde»), `field: 'to'`
   lo pone también en `errors.to` para que el formulario lo muestre debajo del campo.
8. **Respuestas** siempre `{ data, message, errors }`. Todo resource extiende **`App\Http\Resources\ApiResource`**,
   que agrega `message`/`errors`: devolver `XResource::make($m)` o `XResource::collection($paginator)` directo
   (paginado: + `links`/`meta` de Laravel; meta extra con `->additional(['meta' => [...]])`). Con mensaje o status
   distinto: `ApiResponse::ok(XResource::make($m), 'Guardado.', 201)`. Listados paginados por defecto (50 salvo motivo).
9. **Permisos:** catálogo en `App\Domain\Identity\Enums\Permission` (código, no seeder). Cada permiso es un Gate:
   `$user->can(Permission::HoursAllocate->value)`. Las policies combinan permiso + pertenencia (miembro del proyecto).
10. **Multi-empresa desde el día 1:** toda tabla de negocio tiene `company_id` y su modelo usa `BelongsToCompany`.
    En jobs/commands (sin usuario) filtrar con `->forCompany($id)`. **`User` no tiene scope global** (se resuelve antes
    de que exista el usuario autenticado): filtrar siempre `where('company_id', ...)` o vía `$company->users()`;
    el route binding `{user}` ya lo hace. Activos: scope `User::active()` (sirve en relaciones: `members()->active()`).
    Personas elegidas en un formulario: `Identity\Queries\ActiveCompanyPeople::resolveOrFail($companyId, $ids)`.
11. **Tiempo:** UTC en DB, duraciones en **segundos enteros** (`int`), nunca floats de horas; se muestran con
    `Duration::humanize()`. Fechas que tipea una persona (`date`, `from`, `to`) son **locales a su zona**
    (`$user->effectiveTimezone()`) y se convierten a UTC en un solo lugar (`LocalTimeRange`, `LocalDateRange`,
    `ReportPeriod`). Rangos de días: `DateRange::of($from, $to, $maxDays)` (ambos incluidos, tope por pantalla).
12. **Auditoría:** toda mutación relevante llama a `RecordActivity` dentro de la misma transacción.
13. **Rutas por módulo:** `routes/api/<modulo>.php` (se cargan solos dentro de `auth:sanctum` + `active` +
    `RejectMcpTokens` + `throttle:api`, prefijo `/api/v1`); el módulo se prende/apaga por rol con `module:<clave>`.
14. **Tests:** Pest contra MySQL real `newdesk_testing` (nunca la DB de desarrollo). Un archivo por caso de uso / endpoint en
    `tests/Feature/<Modulo>/`. Nombrar por comportamiento, citar el CA (`it('... (CA-05.05)')`).
15. Código y nombres en **inglés**; textos visibles para el usuario en **español**. Comentarios solo para el *por qué*.

### MCP (`app/Mcp/`)

- `Servers/NewDeskServer.php` registra las tools. `Tools/NewDeskTool.php` es el **Template Method**: chequea el
  módulo del rol, llama `run()` y traduce `BusinessRuleViolation` / `AuthorizationException` / not found al mismo
  mensaje que ve la web. Cada tool implementa solo `run()`, `schema()` y (si aplica) `module()`.
- Clases `<Verbo><Sustantivo>Tool` (`GetTaskTool`, `ListMyShiftsTool`, `CommentOnTaskTool`). El nombre que ven los
  clientes (`#[Name('get_task')]`) es contrato: **no se cambia** al renombrar la clase.
- `Support/McpLookup` resuelve códigos/prefijos/personas/ítems de checklist **y autoriza `view`**;
  `Support/McpPresenter` arma las formas compactas para el modelo (sin tarifas ni datos personales de otros).

### Esqueleto de un módulo nuevo

```
app/Domain/Clients/{Models/Client.php, Actions/SaveClient.php, Queries/ClientList.php, Policies/ClientPolicy.php}
app/Http/Controllers/Api/V1/Clients/ClientController.php
app/Http/Requests/Clients/SaveClientRequest.php
app/Http/Resources/ClientResource.php            (extends ApiResource)
routes/api/clients.php
database/migrations/xxxx_create_clients_table.php   (con company_id + índice)
database/factories/ClientFactory.php                 (#[UseFactory] en el modelo)
tests/Feature/Clients/*Test.php
```

## Frontend (`frontend/`, React 19 + Vite + TS strict)

```
src/app/          router y páginas raíz
src/components/   UI compartida sin conocimiento de dominio (layout, botones, modales)
src/features/<modulo>/
   <modulo>Api.ts     llamadas HTTP (tipadas, devuelven el `data` del envelope)
   use<Algo>.ts       hooks de React Query (queries/mutations, query keys)
   types.ts           tipos del dominio
   <Pantalla>Page.tsx / <Componente>.tsx (+ .test.tsx al lado)
src/lib/          api (axios + envelope + ApiError), queryClient, utilidades puras (duration)
```

- Sin `any`; `unknown` + type guard. Archivos ≲ 200 líneas: si crece, extraer.
- Estado de servidor = React Query. Nada de duplicarlo en estado local.
- La UI que oculta botones no es seguridad: el backend decide (policies). `useCan()` es solo un hint visual.
- Tests: Vitest + RTL al lado del componente; E2E Playwright en `e2e/` (desktop + mobile).
