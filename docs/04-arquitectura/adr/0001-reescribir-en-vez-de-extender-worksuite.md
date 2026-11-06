# ADR-0001 — Reescribir newDesk en lugar de extender Worksuite

- Estado: **Aceptado** (2026-11-06, P-01: "proyecto nuevo, Worksuite se descarta")
- Fecha: 2026-11-06

## Contexto

desk.nexosmart.es es Froiden Worksuite: producto comercial, Laravel + Blade + jQuery. Se usa una fracción (proyectos, tareas, timer, perfiles). Lo que más importa al negocio —bolsa de horas con bloqueo— no existe y tocaría el núcleo de time logs.

## Opciones

| | Extender Worksuite | Reescribir (Laravel 12 + React) |
|---|---|---|
| Tiempo al primer valor | Rápido para cambios chicos | R1 en ~6–8 semanas |
| Regla de bolsa de horas | Parche sobre núcleo ajeno; se rompe en cada update | Diseñada desde el modelo de datos |
| Updates del proveedor | Conflictos permanentes o quedar congelados en una versión | N/A |
| UX (12 tabs → 5, mobile) | Pelear contra su UI | Hecha a medida |
| Stack | jQuery/Blade, distinto al resto de NexoSmart | Mismo stack que cuonti y demás proyectos |
| Licencia | Atada a Froiden/CodeCanyon | Propia |
| Riesgo | Bajo inicial, alto acumulado | Migración de datos (mitigado: esquema conocido) |

## Decisión

Proyecto nuevo: monolito modular Laravel 12 (API) + React/Vite (SPA). La migración de datos de Worksuite queda **después** de R1 (P-18).

## Consecuencias

- Hay que construir lo que hoy viene "gratis" (auth, notificaciones, roles): está acotado en R1.
- Quick win independiente: configurar el nombre de la empresa en Worksuite ya (G-22).
