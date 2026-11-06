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
