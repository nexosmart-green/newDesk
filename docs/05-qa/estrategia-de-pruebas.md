# Estrategia de QA / QC

## Principios

- Se prueba contra los **criterios de aceptación** (CA) de `02-requerimientos`. Un RF sin CA no entra a desarrollo.
- Cada feature: ≥ 1 test funcional + 1 E2E del camino feliz (estándar NexoSmart). La bolsa de horas tiene más (abajo).
- Definition of Done: CA verdes en CI · revisado por otro dev · probado en staging con datos migrados reales · sin errores nuevos en `/errors` 24 h después del deploy.

## Pirámide

| Nivel | Herramienta | Foco |
|---|---|---|
| Unitario | Pest | cálculo de disponibles, recorte de tramos, inferencia de sentido de marcas, llegada tarde, zona horaria |
| Feature/API | Pest + DB real (MySQL de test aislada) | policies (403/404), endpoints, invariantes del modelo |
| Concurrencia | Pest + procesos paralelos | dos ▶ simultáneos, N timers agotando la bolsa, códigos de tarea en paralelo |
| Componentes | Vitest + RTL | cronómetro del header, tablero, grilla de turnos |
| E2E | Playwright | flujos críticos abajo, desktop + mobile viewport |

## Casos críticos (suite de regresión obligatoria)

| # | Caso | CA |
|---|---|---|
| QA-01 | No arranca timer sin horas | CA-05.05 |
| QA-02 | 2 timers agotan la bolsa → corte exacto, Consumidas = Bolsa | CA-05.06 |
| QA-03 | Doble clic / dos dispositivos → un solo timer | R-2, CA-05.03 |
| QA-04 | Ampliación destraba proyecto y queda en Actividad | CA-05.04 |
| QA-05 | Timer olvidado se corta y se pide revisión | CA-05.10 |
| QA-06 | Horas manuales que superan la bolsa se rechazan | R-4 |
| QA-07 | "Crear e iniciar" con otro timer activo | CA-04.02 |
| QA-08 | Cerrar tarea / finalizar proyecto / dar de baja usuario corta timers | CA-04.03, CA-03.08, CA-01.10 |
| QA-09 | Colaborador no ve tarifas ni proyectos ajenos (API, no solo UI) | CA-01.09, CA-03.03 |
| QA-10 | Códigos de tarea sin duplicados bajo concurrencia | CA-04.01 |
| QA-11 | Turno + cambio de horario de verano/zona horaria | RNF-04 |
| QA-12 | Marcas de cerradura duplicadas no duplican (R2) | CA-07.02 |
| QA-13 | Silencio de la cerradura dispara alerta (R2) | CA-07.x |
| QA-14 | Migración: totales de horas por proyecto y persona Worksuite = newDesk | `06-migracion` |
| QA-15 | Reloj del día y ▶ apretados a la vez: queda una sola cosa corriendo; 6 inicios dan un solo día (procesos reales) | CA-05.19a/b |
| QA-16 | 6 repartos iguales del mismo día graban una sola vez; caché = ledger | CA-05.19i |
| QA-17 | Reparto en negativo con un ▶ corriendo, cortes y otros ▶ arrancando a la vez: el ▶ previo guarda su tiempo entero, no arranca ninguno nuevo, reconcile ok | CA-05.19f/g |
| QA-18 | Orden de locks: reparto con tarea nueva + ▶ que cambian de proyecto + alta de tareas en paralelo, sin deadlocks y con códigos correlativos | R-6, CA-04.01 |

## QC de datos post-migración

Reporte automático comparando, por proyecto y por persona: cantidad de tareas, horas totales, horas por mes. Tolerancia **0 segundos**. Si no cierra, no hay corte.
