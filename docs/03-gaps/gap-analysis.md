# Gap analysis

Lo que **falta, es ambiguo o es riesgoso** entre lo pedido, lo que hay hoy y lo que hace falta para que funcione. Cada gap tiene dueño y cómo se cierra.

Estado: 🔓 abierto · ✅ cerrado (con la decisión al lado).

## Producto / requerimientos

| ID | Gap | Impacto | Cómo se cierra | Estado |
|---|---|---|---|---|
| G-01 | "Las tabs no se usan" es percepción, no dato. | Podemos sacar algo que alguien usa (ej. Tablero, Hitos, Actividad). | Con el dump de Worksuite (P-19) contar filas por tabla y por mes de los últimos 12 meses: `project_files`, `events`, `discussions`, `project_notes`, `tickets`, `project_milestones`, `taskboard_columns` en uso. | ✅ se toma la palabra del PO: Archivos, Eventos, Gantt, Burndown, Nota, Tickets fuera |
| G-02 | No está definido **qué pasa al agotar horas**: ¿bloqueo duro o aviso? ¿Quién puede saltearlo? | Es la regla central del sistema. | Definido en RF-05: bloqueo duro, solo se destraba con ampliación cargada por Admin. Confirmar con P-05. | ✅ bloqueo duro; destraba quien tenga `hours.allocate` |
| G-03 | No hay **modelo comercial** de horas (bolsa vs abono mensual vs interno). | Sin esto la bolsa no se puede calcular. | P-05. | ✅ sin facturación: bolsa manual + Interno (P-05) |
| G-04 | "Cronometrable **al crear**" es ambiguo: ¿arranca solo siempre? | Si arranca siempre, cargar 10 tareas de planificación consume horas falsas. | RF-04.02: dos botones, "Crear" y "Crear e iniciar". | ✅ propuesta |
| G-05 | Varias personas en el mismo proyecto consumen la bolsa en paralelo; sobreconsumo y carreras. | Proyectos pasados sin que nadie lo note (el problema que queremos resolver). | RF-05 reglas R-1/R-2. | ✅ propuesta |
| G-06 | Timers olvidados abiertos toda la noche. | Horas infladas = bolsa consumida con nada. | RF-05.10 + P-07. | 🔓 |
| G-07 | Horas manuales sin control. | Mismo efecto que G-06. | RF-05.09 + P-06. | ✅ 7 días sin aprobación (P-06) |
| G-08 | Roles: solo se mencionan "dueños" y "empleados". | Permisos mal definidos = fugas (tarifas) o fricción. | Matriz en `02-requerimientos/README.md` + P-02, P-03, P-04. | ✅ roles editables con permisos; Colaborador crea y asigna (P-04) |
| G-09 | Perfil con campos de otro contexto (estado civil, aniversario de matrimonio, preaviso, Slack) y campos mal usados (nombre "NexoSmart Green Juan", departamento único). | Datos basura + datos personales innecesarios. | Tabla de decisiones en `01-relevamiento`. | ✅ propuesta |
| G-10 | Habilidades guardadas como texto libre en "Acerca de". | No se puede buscar ni asignar por skill. | RF-01.06 estructurado + migración parseando "X – NN%". | ✅ propuesta |
| G-11 | País/moneda/zona horaria indefinidos (€ en un equipo de Bahía Blanca). | Reportes de rentabilidad y feriados mal calculados. | P-10. | ✅ por empresa, default ARS / Buenos Aires (P-10) |
| G-12 | Tareas vencidas y "detenidas" sin consecuencia (las 3 de la captura). | Backlog muerto, horas comprometidas que nadie sigue. | RF-09.03 + filtro "vencidas" + estado tipo "bloqueada" con motivo obligatorio. | ✅ propuesta |
| G-13 | Turnos: no se sabe si son fijos, rotativos ni si hay remotos. | Define el modelo de asistencia y la "llegada tarde". | RF-06 soporta los dos + P-15. | 🔓 |
| G-14 | Licencias: el perfil muestra "hojas tomadas" pero no hay requerimiento. | Ausencias sin justificar en asistencia. | RF-08 en R2. | ✅ propuesta |

## Integración cerradura

| ID | Gap | Impacto | Cómo se cierra | Estado |
|---|---|---|---|---|
| G-15 | **Marca/modelo desconocidos.** No sabemos si tiene API, webhook, nube o solo exporta. | Define si la integración es de días o de semanas, o si necesita hardware puente. | P-13. Ver opciones en `integraciones/cerradura/README.md`. | 🔓 Nexxt Smart Wi-Fi (Tuya) → spike de API pendiente |
| G-16 | La cerradura identifica por su propio ID (huella/tarjeta/código), no por email. | Marcas sin dueño. | RF-07.03 mapeo + bandeja "sin asignar". | ✅ propuesta |
| G-17 | Puede no informar entrada vs salida. | Jornadas mal armadas. | CA-07.04 inferencia por alternancia. | ✅ propuesta |
| G-18 | Si la integración se cae, "no hay marcas" parece "nadie vino". | Llegadas tarde/ausencias falsas. | CA-07.x alerta de silencio. | ✅ propuesta |
| G-25 | La cerradura es un **deadbolt**: registra quién abre desde afuera; la salida (perilla interior) no queda a nombre de nadie. | "Egreso" por cerradura no es confiable. | Entrada por cerradura; salida por botón "terminar jornada" / último timer (RF-07.04). P-24. | ✅ propuesta |
| G-26 | Nexxt Home es app OEM de Tuya: su cuenta puede no vincularse al Tuya IoT Platform. | Sin acceso por API. | Spike; plan B re-emparejar con Smart Life; plan C Home Assistant local. | 🔓 |
| G-27 | Cuenta de desarrollador Tuya: el plan de prueba vence y tiene cupo de llamadas. | La integración se corta sola a los meses. | Ver costo en el spike + monitor de silencio (CA-07.x). | 🔓 |
| G-28 | "Configurable por empresa" puede implicar multi-empresa. | Pasar a multi-tenant después es caro. | P-23; `company_id` desde el día 1. | 🔓 |

| G-29 | Comentario obligatorio al detener vs cortes automáticos (nadie está para escribirlo). | Registros sin detalle o timers que no se pueden cortar. | RF-05.17: comentario pendiente que bloquea el próximo timer. | ✅ propuesta |
| G-30 | MCP con token: si los permisos se copian al token al crearlo, un cambio de rol no se refleja; un token filtrado da acceso total. | Escalada / fuga. | RF-12.03 permisos evaluados por request, hash, vencimiento, revocación, prefijo `nd_`, rate limit. | ✅ propuesta |
| G-31 | Lógica duplicada entre API web y MCP. | Reglas de horas que divergen (el MCP saltea el bloqueo). | Ambos llaman a los mismos servicios de dominio; QA-01..03 corren también vía MCP. | ✅ propuesta |

## Datos / migración

| ID | Gap | Impacto | Cómo se cierra | Estado |
|---|---|---|---|---|
| G-19 | Worksuite **no tiene horas vendidas** por proyecto (como bolsa). | Al migrar no sabemos cuánto le queda a cada proyecto. | P-20: planilla cargada por el PO; sin dato → proyecto queda "Interno" hasta cargarlo. | ✅ el dueño carga las horas al dar de alta (P-20) |
| G-20 | Sin acceso a la DB no hay migración ni medición. | Bloquea G-01 y R1. | P-19. | ✅ migración post-R1, ya no bloquea |
| G-21 | Convivencia: si se migra y la gente sigue cargando horas en Worksuite, se pierden. | Horas perdidas. | Corte limpio con freeze de Worksuite (ver `06-migracion`). | ✅ propuesta |

## Técnicos / no funcionales

| ID | Gap | Impacto | Cómo se cierra | Estado |
|---|---|---|---|---|
| G-22 | Instancia actual expone `Default Company` y el producto en el HTML. | Información para un atacante; imagen. | RNF-05. Mientras tanto: configurar el nombre de la empresa en Worksuite (fix de 1 minuto). | 🔓 quick win |
| G-23 | No hay infraestructura de errores (`/errors`, `/health`) exigida para todo proyecto NexoSmart. | Bugs de prod invisibles. | RNF-10, se arma en el scaffolding de Fase 1. | ✅ planificado |
| G-24 | Cronómetro "del navegador" (si se implementa en el cliente) se duplica o se pierde entre dispositivos. | Horas mal registradas. | RF-05.03 timer en servidor + RNF-12. | ✅ propuesta |
