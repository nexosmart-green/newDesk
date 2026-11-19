# RF-14 Asistente de IA (web + Discord)

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
- **Herramientas = las del MCP** (misma definición, mismas acciones) + las de gestión que faltan (RF-14.07). Una sola fuente de verdad.
- **Motor: Claude Code en modo no interactivo (`claude -p`)**, lanzado por el backend desde una cola. No se usa la API de Anthropic ni un SDK. `claude -p` se conecta **solo** al MCP de newDesk (RF-12) con un **token efímero del usuario que pregunta**, así los permisos son los suyos y las herramientas son las mismas del MCP. Modelo configurable (`ASSISTANT_MODEL`, flag `--model`).
- **Escrituras siempre con humano:** con el token del asistente, las tools que cambian datos no ejecutan: crean una **propuesta** que la persona confirma o cancela (web o botón de Discord); recién ahí corre la acción de dominio.
- **Reportes programados sin IA para los números:** el scheduler arma los datos con las queries existentes; la IA solo redacta el **resumen en texto**.

## Requerimientos

| ID | Requerimiento | Prio |
|---|---|---|
| RF-14.01 | Chat "Asistente" en la web (solo con `assistant.use`): conversación con historial por usuario, respuestas en texto con tablas, sugerencias de pedidos frecuentes. | M |
| RF-14.02 | **Confirmación antes de escribir:** toda acción que cambia datos (cargar/quitar horas, alta/edición de cliente o proyecto, mover tarea, aprobar/rechazar ampliación…) se muestra como propuesta con **Confirmar / Cancelar**; recién al confirmar se ejecuta. Lecturas y reportes no piden confirmación. | M |
| RF-14.03 | Permisos de quien pregunta, evaluados en cada herramienta (mismas policies que web/MCP). Si no puede, el asistente lo dice claro ("Tu rol no puede cargar horas"). | M |
| RF-14.04 | Todo lo que hace queda en Actividad con `source = assistant` y el canal (web/discord). | M |
| RF-14.05 | **Reportes en archivo:** el asistente puede devolver Excel o PDF descargable (horas por proyecto/cliente/persona, bolsas, vencidas). | M |
| RF-14.06 | **Resumen de la semana en texto:** a pedido ("resumime la semana") y **programado** (lunes 9:00, hora de la empresa): mensaje breve en castellano con horas de la semana por proyecto y persona, bolsas por agotarse, tareas vencidas, ampliaciones pendientes y lo destacable (picos, proyectos sin movimiento). Se publica en el canal de Discord configurado y por mail a los administradores; también queda en el chat web. Números calculados por el sistema, no por la IA. | M |
| RF-14.07 | **Herramientas de gestión nuevas** (también disponibles por MCP): reporte de horas de todos los proyectos, bolsas por agotarse, horas por cliente, horas por persona (equipo), tareas vencidas del equipo, clientes (listar/alta/editar/activar-inactivar), proyectos (crear/editar/estado/miembros), ampliaciones (listar/aprobar/rechazar), personas (listar), turnos del equipo. | M |
| RF-14.08 | **Discord:** bot de newDesk en el servidor de la empresa. Vinculación de cuenta una vez (código desde Mi perfil → Conexiones, `/newdesk vincular CÓDIGO`); comando `/newdesk <pedido>`; respuesta en hilo; confirmaciones con botones que solo puede apretar quien pidió; reportes como adjunto. Mensajes en texto libre por DM al bot (fase 5, 2026-11-19). | M |
| RF-14.09 | **Límites:** tope de gasto mensual de IA configurable; rate limit por usuario; si se alcanza el tope, el asistente avisa y deja de responder hasta el mes siguiente (los reportes programados con números siguen saliendo, sin el texto redactado). | M |
| RF-14.10 | Configuración (Admin): habilitar/deshabilitar el asistente, modelo, tope mensual, canal de Discord para el resumen semanal, destinatarios del mail. | S |
| RF-14.11 | Conector de claude.ai (OAuth en el MCP, RF-12.08) para usar las mismas herramientas desde la app de Claude. | C (después) |

### Cómo quedó el resumen semanal (2026-11-17)

- Sale apagado; lo prende un Admin en Configuración → Asistente. Lunes 9:00 hora de la empresa, una vez por semana.
- Mail: a la lista que cargue el Admin; vacía = los administradores activos. Chat web: solo a quien tiene `assistant.use`,
  `projects.manage` y el módulo de reportes (nunca usuarios cliente).
- Discord: el canal de resumen de monitoreo (`DISCORD_SUMMARY_CHANNEL_ID`, mismo bot). Es del server, no de la empresa:
  sale ahí solo si la empresa lo activa (`assistant_summary_discord`).
- Números siempre del sistema (plantilla). La IA puede reescribirla, y su texto solo se usa si conserva cada
  "nombre + horas", los mismos números y no agrega links ni menciones; si no, sale la plantilla.

### Cómo quedó Discord (2026-11-17)

- Misma app y bot que monitoreo (RF-13). `/newdesk pedir pedido:…` y `/newdesk vincular codigo:…`.
- **Respuestas efímeras, no en hilo** (desvío de RF-14.08, decidido por CA-14.09): solo las ve quien preguntó; en un hilo
  de un canal compartido todos verían clientes y horas.
- Vincular: "Conectar Discord" en el asistente web da un código de un solo uso (10 min). El asistente **solo** actúa por
  vínculos hechos así (`discord_verified_at`); un id cargado por un Admin en monitoreo sirve para menciones, no para actuar
  en nombre de la persona. Una cuenta de Discord, un solo usuario de newDesk.
- Configuración (una vez): en el portal de desarrolladores de la app de monitoreo copiar Application ID y Public Key a
  `DISCORD_APPLICATION_ID` / `DISCORD_PUBLIC_KEY`, poner como Interactions Endpoint URL `https://<dominio>/api/discord/interactions`
  y correr `php artisan assistant:discord-commands`.
- **DM al bot (fase 5, 2026-11-19):** se le escribe al bot por mensaje directo, en texto libre, sin `/newdesk`. Mismas
  reglas (vínculo hecho por la persona, `assistant.use`, topes) + 6 mensajes por minuto por cuenta. La respuesta llega
  como mensaje nuevo en el DM (es privado: no hace falta efímero), con los mismos botones y adjuntos. Lo recibe un proceso
  conectado al gateway de Discord (`php artisan assistant:discord-gateway`), que `supervisor:sync` agrega solo cuando
  están `DISCORD_BOT_TOKEN` y `DISCORD_APPLICATION_ID`. No necesita intents privilegiados (solo `DIRECT_MESSAGES`). Los
  mensajes en canales del servidor no se leen.

## Criterios de aceptación

- **CA-14.02** Dado un Admin que pide "cargale 20 h a bhn", cuando el asistente responde, entonces muestra la propuesta (proyecto, horas, motivo) y **no** cambia nada hasta que el mismo usuario confirma; si cancela, no queda ningún cambio.
- **CA-14.03** Dado un usuario sin `hours.allocate` (aunque tenga `assistant.use`), cuando pide cargar horas, entonces el asistente responde que su rol no puede hacerlo y no propone la acción.
- **CA-14.06** Dado el lunes 9:00, cuando corre el resumen programado, entonces el canal de Discord configurado recibe un mensaje con las horas de la semana anterior que coinciden con el reporte de horas de la web para ese rango.
- **CA-14.08** Dado un usuario de Discord no vinculado, cuando usa `/newdesk`, entonces el bot le explica cómo vincular la cuenta y no ejecuta nada. Dado un botón Confirmar, cuando lo aprieta otra persona, entonces no se ejecuta.
- **CA-14.09** Los datos del resumen (clientes, horas por persona) solo salen al canal y mails configurados por un Admin.

## Fuera de alcance (por ahora)

Usuarios no administradores, mensajes libres en canales del servidor (solo DM), Slack/WhatsApp, voz, que el asistente edite configuración o roles.
