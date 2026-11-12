# Repaso de calidad (2026-11-12)

Pedido de Juani: clean code apto IA, patrones, arquitectura, SOLID; loaders que solo afecten lo que se recarga; errores
correctos y fáciles de entender; modals con tamaño justo; sacar lo genérico de IA; diseño claro y lindo de usar — "a
rajatable en todos lados", con `frontend-design` + `ui-ux-pro-max`.

Auditorías (solo lectura) hechas por 4 agentes; acá el plan consolidado. Rama `dev_green`. **No tocar** los archivos que
modifica `feat/monitoreo` salvo que sea imprescindible (lista en `tasks/subtareas-adjuntos.md`).

## B1 — Backend: mensajes, i18n y bugs (primero)
- [x] `lang/es/validation.php` en voseo + `attributes` (hoy salen claves crudas `validation.required` en web y MCP).
- [x] Fallbacks del envelope en `bootstrap/app.php` (401/404/422/429/4xx/500) con texto que diga qué pasó y qué hacer.
- [x] Mensajes puntuales (credenciales, 2FA, HEIC, tipo de adjunto, rangos, ciclos "reporta a", cronómetro ocupado…).
- [x] MCP: errores accionables para el modelo ("usá list_my_tasks…", "no reintentes").
- [x] Policies con `Response::deny()` sin texto → texto específico.
- [x] BUG: `ProjectHoursController::entries` compara fechas locales contra UTC → `Queries/ProjectTimeEntries` con la conversión de `TimeEntryHistory`.
- [x] "Bolsa por agotarse" con 3 definiciones → una (`Project::scopeRunningOut` / `TimeEntry::runningSecondsSubquery`).
- [x] Deriva web/MCP: `Queries/TimerState`, `Support/LocalTimeRange`, `Shared/Support/DateRange` (límite de rango único, también en timesheet/calendar/grid web), `Support/ReportPeriod`.

## B2 — Backend: arquitectura
- [x] Lógica fuera de controllers: notificaciones (preferencias), reset de contraseña (+ actividad), feriados/plantillas (+ actividad), feed .ics.
- [x] Duplicados de turnos: `ActiveCompanyPeople::resolveOrFail`, `ShiftCellWriter`.
- [x] Actions con varios casos de uso → una por caso (`ResolveHourExtension`, `IssueCalendarFeed`, `AutoStopTimers` con `TimerCutter`).
- [x] Guards/queries fuera de `Actions/` → `Support/` / `Queries/`.
- [x] Autorización unificada por Policy (horas, ampliaciones, comentario pendiente) en web y MCP.
- [x] Should-fix: queries inline → Query classes; FormRequests en vez de `validate()` inline con constantes compartidas web/MCP; enums en vez de literales; `ApiResource` base; dependencias ocultas; nombres de tools MCP `<Verbo><Sustantivo>Tool`; código muerto; `AttachmentFileType`.
- [x] Docs: `convenciones-de-codigo.md` y `arquitectura.md` al día.

## F1 — Frontend: loaders y datos
- [x] Guardados que esperan TODOS los refetch → esperar solo lo de la pantalla, el resto en background.
- [x] Un cambio bloquea todo el tablero/lista → pendiente por fila/tarjeta (+ movimiento optimista en el tablero).
- [x] Invalidaciones demasiado amplias (todos los proyectos, todo lo de la tarea) → claves precisas.
- [x] Barra de progreso también durante la carga de rutas lazy; sacar el Suspense muerto.
- [x] Indicador de "datos viejos" al cambiar filtro/página en todas las listas (`StaleContent`).
- [x] Timer: los botones de cada fila no re-renderizan cada 30 s (`select`).
- [x] `lib/queryKeys.ts` único; semana/fechas a `lib/`; hooks de search params y tabs compartidos; `AsyncSelectField`; `PageState` con `skeleton`.

## F2 — Frontend: errores y modals
- [x] 422 de un campo que no está en pantalla → toast (hoy se pierde en silencio).
- [x] 401 en sesión (texto + login), 413, timeout, cuerpos HTML → mensajes claros.
- [x] Guard de cambios sin guardar en los diálogos con formulario; el fondo no cierra un formulario con cambios.
- [x] Tamaños: BulkAssign y Turno fijo → xl en 2 columnas (sin scroll dentro de scroll); EditEntry, Plantilla, Invitar → md.
- [x] Confirmaciones: estilo peligro + verbo real (estado de proyecto, copiar semana), títulos "¿Borrar «X»?" + botón "Borrar"; DeleteRole a ConfirmDialog; bloqueo del timer con acción.
- [x] Un verbo por acción: botón y toast iguales ("… guardado"), toasts de catálogos que nombran el ítem.

## F3 — Frontend: diseño sin lo genérico de IA
Dirección: **"un registro de trabajo, no un dashboard SaaS"**. Lo único memorable: el **cronómetro en rosa** (#f6beb6 =
"corriendo ahora", no se usa para otra cosa); todo lo demás quieto en teal + tinta. Paleta de desk intacta.
Capturas de la auditoría: `C:\Users\GREEN\AppData\Local\Temp\claude\4fe530b1c11d8e8d\3bbe2057-d08e-4645-bc91-ce28c5a09f6b\scratchpad\audit\`.

Tokens:
- Superficies dark: canvas #1b222c · surface #232b36 · raised (solo popover/dialog) #2b3441 · line #2f3946 · field #1b222c.
  Light: canvas #e8eef3 · surface #fff · line #d3dbe3 · field #fff con borde #c3ced9 (hoy los inputs light parecen deshabilitados).
- Teal #23797a solo botón primario y selección; links accent. Danger solo destructivo y vencido.
- Tipo (Onest, tabular en todo número): 12 meta · 13 secundario · 14 cuerpo/UI · 16/600 sección · 20/600 título de página · 28/600 cronómetro y números clave.
- Radios: 4 chips/checkbox/barras · 6 inputs/botones · 10 contenedores · 14 diálogos/popovers · full solo avatar, pill del timer, punto de estado.
- Elevación: 0 en página (tono, no sombra) · popover shadow-lg · dialog shadow-2xl. Sin `shadow-sm` en botones.
- Densidad: controles 36px en desktop, 44px con `pointer:coarse`; filas 44; padding de página 24; ancho hasta max-w-7xl.

Cambios (orden de impacto):
- [ ] Tokens en `index.css` (+ variante `coarse`), Button/TextField/SelectField con los nuevos radios/alturas/`bg-field`, variante `icon`.
- [ ] Un solo título: sacar el del Header (queda el h1 de la página); header mobile: menú · timer · campana · avatar.
- [ ] Cronómetro rosa: pill `bg-live` con título de tarea; fila de la tarea corriendo con regla rosa y tiempo en vez de ▶; stop nunca `danger`.
- [ ] Inicio: banda "Ahora" (tarea corriendo con tiempo grande y Detener, o "Seguí con…" e Iniciar); contadores como frase con links; barra de hoy en rosa.
- [ ] Filtros en una fila: búsqueda con ícono sin label visible, selects cuyo valor nombra la dimensión, "Más filtros (n)"; mobile: búsqueda + "Filtros".
- [ ] Detalle de tarea: columna principal una superficie (secciones por título y espacio, no tarjetas); lateral como lista de propiedades; "Mover a otro proyecto" a un menú "⋯".
- [ ] Semana / calendario: un contenedor, una fila por día, días vacíos agrupados ("Mié 11 a vie 13: sin registros").
- [ ] PeriodNav con chevrons + "Hoy", sin "←/→" en texto.
- [ ] Sin `font-mono` (salvo código real: tokens, 2FA, snippets) ni `uppercase`; códigos de tarea como se guardan, 500, muted.
- [ ] Reescribir los 24 "A · B · C" como estructura (avatar, línea muted, ícono); plural "1 miembro".
- [ ] Proyectos: prefijo después del nombre, badge solo si no está Activo, barra de horas sin "consumido" por fila.
- [ ] Turnos/equipo: celdas vacías en blanco (+ al hover para quien gestiona), finde/feriado con fondo, chips sin truncar, zona horaria solo si difiere ("Hora de Buenos Aires"), sin animate-ping.
- [ ] iCal como botón secundario en el encabezado; estado de proyecto como badge editable con menú.
- [ ] Login con marca (newDesk sobre el color del sidebar); notificaciones sin "Sin mensaje.", no leídas con punto rosa.
- [ ] `EmptyState` único (ícono, una frase, una acción, sin borde punteado).
- [ ] Posible bug: en bb1-1 el select de Estado sale vacío (¿el estado actual no está entre las opciones?).

## Cierre
- [ ] Suites completas (back, front local + UTC), E2E, capturas desktop/mobile claro/oscuro, review independiente.
- [ ] Commit/push `dev_green`, CI verde, merge a `main`.

## Estado al cortar (2026-11-12, noche)
- Hecho y pusheado en `dev_green` (NO en `main`): B1, B2, F1 (commit af37785, CI verde) + F2 y mensajes de éxito del backend que nombran el ítem (commit de cierre).
- F2: suite local 262 tests (2 que fallaban por textos viejos ya corregidos y verdes). Falta re-confirmar `TZ=UTC npx vitest run` + `npm run build` (se cortó al parar) — el CI del push lo verifica.
- Backend tests no corridos localmente desde los mensajes nuevos (MySQL caído por falta de RAM): los verifica el CI.
- **Mañana:** revisar CI del último commit → F3 (rediseño, ver sección F3) → cierre (suites, E2E con MySQL levantado, capturas claro/oscuro/mobile, review independiente) → merge a `main`.
- Entorno local: MySQL, Redis y servidores apagados por falta de memoria; Vitest limitado a 2 workers.
