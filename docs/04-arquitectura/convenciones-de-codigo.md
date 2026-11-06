# Convenciones de código

Regla del PO (2026-11-06): **clean code, SOLID, patrones de diseño, código apto para IA, arquitectura ordenada.**
"Apto para IA" = un agente (o una persona nueva) encuentra dónde va cada cosa sin leer todo el repo:
nombres que dicen qué hacen, un lugar por responsabilidad, convenciones repetidas igual en todos los módulos.

## Backend (`backend/`, Laravel 12)

### Capas

```
HTTP / MCP (adaptadores delgados)       app/Http/Controllers/Api/V1/<Modulo>/  ·  app/Mcp/Tools/
   │  validan entrada (FormRequest), autorizan (Policy), llaman UNA action, devuelven un Resource
   ▼
Dominio                                  app/Domain/<Modulo>/
   Actions/      un caso de uso por clase, invocable (__invoke). Transacción + reglas + RecordActivity
   Models/       Eloquent: relaciones, casts, scopes. Sin reglas de negocio de casos de uso
   Policies/     quién puede (ver/editar/...). 404 en vez de 403 cuando no debe saber que existe (CA-03.03)
   Enums/        estados y catálogos fijos (string-backed)
   Events/ Jobs/ Notifications/ Queries/ (lecturas complejas)
   ▼
Shared                                   app/Domain/Shared/
   Concerns/BelongsToCompany   scope + company_id automático
   Activity/RecordActivity     auditoría (único punto de escritura de activity_log)
   Exceptions/BusinessRuleViolation   regla de negocio rechazada → 422 con mensaje humano + `code`
```

### Reglas

1. **Una sola capa de reglas.** Controllers y tools MCP nunca contienen reglas; llaman a la misma action
   (`StartTimer`, `CreateTask`, `AllocateHours`…). Si una tool MCP necesita algo que la web no hace, va a una action.
2. **SRP:** una action = un caso de uso. Si el nombre necesita "And", son dos actions.
3. **OCP/DIP:** dependencias por constructor (inyección del container). Integraciones externas (cerradura, mail)
   detrás de una interfaz en `Domain/<Modulo>/Contracts` con adaptador en `app/Infrastructure/`.
4. **Errores de negocio** con `BusinessRuleViolation(message, ruleCode)`. El mensaje es para el usuario final y es el mismo en web y MCP.
5. **Respuestas** siempre `{ data, message, errors }`: `XResource::make()` / `XResource::collection($paginator)` o `ApiResponse::ok()`.
   Listados paginados por defecto.
6. **Permisos:** catálogo en `App\Domain\Identity\Enums\Permission` (código, no seeder). Cada permiso es un Gate:
   `$user->can(Permission::HoursAllocate->value)`. Las policies combinan permiso + pertenencia (miembro del proyecto).
7. **Multi-empresa desde el día 1:** toda tabla de negocio tiene `company_id` y su modelo usa `BelongsToCompany`.
   En jobs/commands (sin usuario) filtrar con `->forCompany($id)`.
8. **Tiempo:** UTC en DB, duraciones en **segundos enteros** (`int`), nunca floats de horas.
9. **Auditoría:** toda mutación relevante llama a `RecordActivity` dentro de la misma transacción.
10. **Rutas por módulo:** `routes/api/<modulo>.php` (se cargan solos dentro de `auth:sanctum` + `active`, prefijo `/api/v1`).
11. **Tests:** Pest contra MySQL real `newdesk_testing` (nunca la DB de desarrollo). Un archivo por caso de uso / endpoint en
    `tests/Feature/<Modulo>/`. Nombrar por comportamiento, citar el CA (`it('... (CA-05.05)')`).
12. Código y nombres en **inglés**; textos visibles para el usuario en **español**. Comentarios solo para el *por qué*.

### Esqueleto de un módulo nuevo

```
app/Domain/Clients/{Models/Client.php, Actions/CreateClient.php, Policies/ClientPolicy.php}
app/Http/Controllers/Api/V1/Clients/ClientController.php
app/Http/Requests/Clients/StoreClientRequest.php
app/Http/Resources/ClientResource.php
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
