# newDesk — Plataforma de gestión interna de NexoSmart

Reemplazo de `desk.nexosmart.es` (hoy: **Froiden Worksuite**, Laravel + jQuery, confirmado por los assets `vendor/froiden-helper`).

**Objetivo:** quedarnos con lo que se usa de verdad (proyectos, tareas, horas, equipo, turnos, asistencia), hacerlo bien y sacar el resto.

## Estado

**Fase 0 — Relevamiento y requerimientos.** Todavía no hay código. Los documentos de `docs/` son la fuente de verdad del alcance.

## Mapa de carpetas

| Carpeta | Para qué |
|---|---|
| `requerimientos-inbox/` | **Acá cae todo pedido nuevo** (texto, captura, audio transcripto). Se procesa y se pasa a `docs/02-requerimientos`. |
| `docs/00-vision/` | Visión, alcance, qué entra y qué no, métricas de éxito. |
| `docs/01-relevamiento/` | Cómo funciona hoy Worksuite: pantallas, campos, qué se usa y qué no. |
| `docs/02-requerimientos/` | Requerimientos funcionales por módulo (`RF-xx`) + no funcionales (`RNF`). Cada uno con criterios de aceptación. |
| `docs/03-gaps/` | Gaps detectados y **preguntas abiertas para el PO**. Lo que bloquea está marcado. |
| `docs/04-arquitectura/` | Stack, modelo de datos, decisiones (ADR). |
| `docs/05-qa/` | Estrategia de pruebas y casos críticos. |
| `docs/06-migracion/` | Cómo pasamos los datos de Worksuite a newDesk. |
| `docs/07-backlog/` | Épicas, prioridades (MoSCoW) y releases. |
| `docs/08-handoff/` | Prompts de ejecución para la sesión implementadora. |
| `integraciones/cerradura/` | Integración con la cerradura digital (marcas de ingreso/egreso). |
| `backend/` | Laravel 12 (API). Vacío hasta cerrar las preguntas bloqueantes. |
| `frontend/` | React + Vite (SPA). Vacío hasta cerrar las preguntas bloqueantes. |
| `tasks/` | `todo.md` (plan de trabajo) y `lessons.md` (lecciones). |

## Por dónde empezar

1. Leé `docs/03-gaps/preguntas-abiertas.md` y contestá las **🔴 bloqueantes**.
2. Revisá `docs/07-backlog/backlog.md` (alcance del MVP).
3. Con eso cerrado, arrancamos la Fase 1 (scaffolding backend + frontend).
