# newDesk

Reemplazo de desk.nexosmart.es (Froiden Worksuite). Ver `README.md`.

## Reglas del proyecto

- **Fuente de verdad del alcance:** `docs/02-requerimientos`. No implementar nada que no tenga RF con criterios de aceptación.
- Pedidos nuevos → `requerimientos-inbox/` primero, después se absorben en un RF.
- Dudas que cambian el comportamiento → `docs/03-gaps/preguntas-abiertas.md`, no inventar.
- La bolsa de horas (RF-05) es crítica: toda modificación lleva tests de concurrencia (QA-01..08) y no se mergea sin ellos.
- Timers y cálculos de tiempo siempre en servidor, UTC, segundos enteros.
- Stack: Laravel 12 (`backend/`) + React/Vite/TS (`frontend/`), MySQL, Redis, Reverb, Resend, Supervisord.
- `/health` y `/errors` (`/bug-report`) se arman en E0; `.claude/bug-report.json` va en `.gitignore`.
- La DB de Worksuite nunca se modifica: solo lectura/dump.

## Código

**Clean code, SOLID, patrones de diseño, apto para IA.** Antes de escribir código leé
`docs/04-arquitectura/convenciones-de-codigo.md` (capas, dónde va cada cosa, esqueleto de módulo).
Resumen: controllers y tools MCP delgados → **una Action de dominio** por caso de uso → `BusinessRuleViolation`
para reglas rechazadas → Resources con envelope `{ data, message, errors }`.

## Comandos

```bash
# backend
cd backend && composer install && cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # DB `newdesk` (dev). Usuarios: admin@ / gestor@ / colaborador@nexosmart.com — password
php artisan test                     # siempre contra `newdesk_testing` (forzado en phpunit.xml)
vendor/bin/pint                      # formato
php artisan supervisor:sync --dry-run

# frontend
cd frontend && npm install
npm run dev                          # proxy /api y /sanctum → http://localhost:8000
npm test && npm run typecheck && npm run lint && npm run build
```
