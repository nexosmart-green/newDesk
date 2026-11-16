# RF-13 Asistente de IA (web + Discord)

Origen: pedido de Juani 2026-11-16 — "un agente que mi jefe o las personas con permiso le puedan hablar e interactuar en
lenguaje natural pidiéndole reportes o cosas para hacer en el sistema", "que se pueda hacer desde Discord", "por ahora
solo los administradores", "que genere no solo reportes en archivos sino un resumen de la semana en texto en un mensaje".

## Objetivo

Un **asistente de newDesk** al que el dueño y los administradores le hablan en lenguaje natural — desde la web o desde
Discord — para pedir reportes, resúmenes y acciones ("cargale 20 h a la bolsa de bhn", "dame de alta el cliente X",
"¿qué bolsas se están por agotar?"). Hace **exactamente** lo que el rol de quien pregunta puede hacer en la web: mismas
policies y acciones de dominio que el MCP (RF-12), ningún atajo.

## Decisiones

- **Quién:** permiso nuevo `assistant.use`. Por defecto **solo el rol Admin** lo tiene (configurable por rol en Configuración → Roles).
- **Un solo núcleo, varios canales:** el asistente vive en el backend (dominio `Assistant`); la web y Discord son canales. Slack/WhatsApp se suman después sin reescribir.
- **Herramientas = las del MCP** (misma definición, mismas acciones) + las de gestión que faltan (RF-13.07). Una sola fuente de verdad.
- **Modelo:** Claude Opus 5.5 vía API (SDK oficial de PHP, tool runner). Configurable (`ASSISTANT_MODEL`); Sonnet 5.5 si se prioriza costo.
- **Reportes programados sin IA para los números:** el scheduler arma los datos con las queries existentes; la IA solo redacta el **resumen en texto**.

## Requerimientos

| ID | Requerimiento | Prio |
|---|---|---|
| RF-13.01 | Chat "Asistente" en la web (solo con `assistant.use`): conversación con historial por usuario, respuestas en texto con tablas, sugerencias de pedidos frecuentes. | M |
| RF-13.02 | **Confirmación antes de escribir:** toda acción que cambia datos (cargar/quitar horas, alta/edición de cliente o proyecto, mover tarea, aprobar/rechazar ampliación…) se muestra como propuesta con **Confirmar / Cancelar**; recién al confirmar se ejecuta. Lecturas y reportes no piden confirmación. | M |
| RF-13.03 | Permisos de quien pregunta, evaluados en cada herramienta (mismas policies que web/MCP). Si no puede, el asistente lo dice claro ("Tu rol no puede cargar horas"). | M |
| RF-13.04 | Todo lo que hace queda en Actividad con `source = assistant` y el canal (web/discord). | M |
| RF-13.05 | **Reportes en archivo:** el asistente puede devolver Excel o PDF descargable (horas por proyecto/cliente/persona, bolsas, vencidas). | M |
| RF-13.06 | **Resumen de la semana en texto:** a pedido ("resumime la semana") y **programado** (lunes 9:00, hora de la empresa): mensaje breve en castellano con horas de la semana por proyecto y persona, bolsas por agotarse, tareas vencidas, ampliaciones pendientes y lo destacable (picos, proyectos sin movimiento). Se publica en el canal de Discord configurado y por mail a los administradores; también queda en el chat web. Números calculados por el sistema, no por la IA. | M |
| RF-13.07 | **Herramientas de gestión nuevas** (también disponibles por MCP): reporte de horas de todos los proyectos, bolsas por agotarse, horas por cliente, horas por persona (equipo), tareas vencidas del equipo, clientes (listar/alta/editar/activar-inactivar), proyectos (crear/editar/estado/miembros), ampliaciones (listar/aprobar/rechazar), personas (listar), turnos del equipo. | M |
| RF-13.08 | **Discord:** bot de newDesk en el servidor de la empresa. Vinculación de cuenta una vez (código desde Mi perfil → Conexiones, `/newdesk vincular CÓDIGO`); comando `/newdesk <pedido>`; respuesta en hilo; confirmaciones con botones que solo puede apretar quien pidió; reportes como adjunto. Mensajes en texto libre / DM: fase 2. | M |
| RF-13.09 | **Límites:** tope de gasto mensual de IA configurable; rate limit por usuario; si se alcanza el tope, el asistente avisa y deja de responder hasta el mes siguiente (los reportes programados con números siguen saliendo, sin el texto redactado). | M |
| RF-13.10 | Configuración (Admin): habilitar/deshabilitar el asistente, modelo, tope mensual, canal de Discord para el resumen semanal, destinatarios del mail. | S |
| RF-13.11 | Conector de claude.ai (OAuth en el MCP, RF-12.08) para usar las mismas herramientas desde la app de Claude. | C (después) |

## Criterios de aceptación

- **CA-13.02** Dado un Admin que pide "cargale 20 h a bhn", cuando el asistente responde, entonces muestra la propuesta (proyecto, horas, motivo) y **no** cambia nada hasta que el mismo usuario confirma; si cancela, no queda ningún cambio.
- **CA-13.03** Dado un usuario sin `hours.allocate` (aunque tenga `assistant.use`), cuando pide cargar horas, entonces el asistente responde que su rol no puede hacerlo y no propone la acción.
- **CA-13.06** Dado el lunes 9:00, cuando corre el resumen programado, entonces el canal de Discord configurado recibe un mensaje con las horas de la semana anterior que coinciden con el reporte de horas de la web para ese rango.
- **CA-13.08** Dado un usuario de Discord no vinculado, cuando usa `/newdesk`, entonces el bot le explica cómo vincular la cuenta y no ejecuta nada. Dado un botón Confirmar, cuando lo aprieta otra persona, entonces no se ejecuta.
- **CA-13.09** Los datos del resumen (clientes, horas por persona) solo salen al canal y mails configurados por un Admin.

## Fuera de alcance (por ahora)

Usuarios no administradores, mensajes libres por DM en Discord (fase 2), Slack/WhatsApp, voz, que el asistente edite configuración o roles.
