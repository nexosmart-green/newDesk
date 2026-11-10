# RF-05 Cronómetro y bolsa de horas ⭐

El corazón del sistema. Es lo que Worksuite no resuelve.

## Conceptos

- **Registro de horas** (`time_entry`): usuario, tarea, inicio, fin, duración, origen (`timer` | `manual` | `migración`), nota.
- **Bolsa de horas** del proyecto = **suma de asignaciones** (`hour_allocation`): cada una con horas (+/-), motivo, quién, cuándo y referencia opcional en texto. No hay facturación (P-05): las carga el dueño o quien tenga permiso `hours.allocate`. La primera es la inicial; las siguientes son **ampliaciones**. Nunca se edita una asignación: se agrega otra (auditable).
- **Consumidas** = suma de duraciones de registros del proyecto (incluye timers corriendo, calculados a "ahora").
- **Disponibles** = Bolsa − Consumidas.

## Requerimientos

| ID | Requerimiento | Prio |
|---|---|---|
| RF-05.01 | Cronómetro ▶/■ en cada tarea asignada y **cronómetro global en el header** visible en toda la app (como hoy). | M |
| RF-05.02 | **Un solo timer activo por usuario.** Iniciar otro detiene y guarda el anterior. | M |
| RF-05.03 | El timer vive en el **servidor** (hora de inicio guardada), no en el navegador: cerrar la pestaña, cambiar de PC o de dispositivo no lo corta ni lo duplica. | M |
| RF-05.04 | Quien tenga `hours.allocate` carga horas al crear el proyecto y agrega **ampliaciones** con motivo obligatorio. | M |
| RF-05.05 | **Bloqueo:** si Disponibles ≤ 0, no se puede iniciar un timer ni cargar horas manuales en ese proyecto. El botón muestra "Sin horas disponibles — pedir ampliación". | M |
| RF-05.06 | **Corte automático:** un timer corriendo se detiene solo cuando el proyecto llega a 0 disponibles (job cada minuto + chequeo al detener). Notifica al usuario y a Admin. | M |
| RF-05.07 | Alertas al 80% y al 100% de consumo (Admin + líder). | M |
| RF-05.08 | **Pedido de ampliación** desde el bloqueo: el colaborador lo pide con un clic, quien tenga `hours.allocate` aprueba (carga horas) o rechaza. | S |
| RF-05.09 | Carga manual de horas (olvidé el timer): fecha, desde-hasta, tarea, nota obligatoria. Hasta 7 días atrás sin aprobación (configurable, P-06 ✅); más atrás, solo con permiso. | M |
| RF-05.10 | Timer olvidado: si corre más de N horas (default 10) o pasa la medianoche ⚠P-07, se detiene y pide confirmar/corregir. | M |
| RF-05.11 | Tab **Horas** del proyecto: registros por persona/tarea/fecha, total, historial de asignaciones de la bolsa. | M |
| RF-05.12 | "Mi semana": hoja de horas personal (lun–dom) con total diario. | S |
| RF-05.13 | ~~Mantenimiento mensual~~ — descartado (P-05: no hay facturación). | W |
| RF-05.14 | Proyecto tipo **Interno**: sin tope, no bloquea, pero registra. | M |
| RF-05.15 | Admin puede corregir o borrar registros de cualquiera (queda en Actividad). Un colaborador corrige los suyos dentro de la ventana de 7 días. | M |
| RF-05.16 | **Comentario obligatorio al detener** (pedido PO 2026-11-06): "¿qué hiciste?" — mínimo 10 caracteres, en web y en MCP. Sin comentario, el timer no se detiene. Si el corte lo provoca iniciar otro timer o "Crear e iniciar", se pide el comentario del anterior en el mismo paso. | M |
| RF-05.17 | **Cortes automáticos** (sin horas, olvidado, tarea cerrada, proyecto finalizado, baja): el registro queda con **comentario pendiente**. El usuario no puede iniciar un nuevo timer hasta completarlo (banner en web, aviso en `whoami`/`start_timer` por MCP). | M |
| RF-05.18 | El comentario del registro se muestra en la tab Horas, en "Mi semana" y en el reporte: es el detalle de qué se hizo con las horas del cliente. | M |
| RF-05.19 | **Reloj del día** (pedido 2026-11-10): alternativa al ▶ por tarea. Un reloj por persona y por día, sin tarea, que se inicia y se frena desde el header. Al frenarlo se **reparte** en partes (desde–hasta, proyecto, tarea mía o nueva, "¿qué hiciste?"); cada parte es un registro de horas normal (`source = day_clock`). Los huecos no se cuentan. Una parte puede dejar la bolsa en negativo: se guarda igual y se avisa a Admin + líder. Convive con el ▶ (nunca los dos a la vez) y un día sin repartir bloquea el ▶ y otro día. Si se olvida, se corta a la medianoche local. Solo web (MCP lo muestra en `whoami`). | M |

## Reglas de concurrencia (lo que suele romperse)

- **R-1** Varias personas cronometrando el mismo proyecto a la vez consumen la bolsa en paralelo. El corte (RF-05.06) se evalúa con la suma de **todos** los timers corriendo. Sobreconsumo máximo tolerado: 1 minuto × timers activos (granularidad del job). Al cortar, el último tramo se recorta para que Consumidas = Bolsa exacta.
- **R-2** Iniciar timer es **atómico** con la verificación de disponibles (transacción + lock de fila del proyecto). Dos clics simultáneos no generan dos timers.
- **R-3** Una ampliación cargada con timers ya cortados no los reanuda: el usuario vuelve a dar ▶.
- **R-4** Horas manuales que superarían la bolsa: se rechazan enteras (no se recortan en silencio). **Excepción RF-05.19:** el reparto del reloj del día se guarda entero aunque deje la bolsa en negativo, y avisa.
- **R-6** (RF-05.19) Cuando un reparto deja un proyecto en negativo, los ▶ que corrían en él quedan **sin tope** (`uncapped`): no los corta el corte por bolsa (RF-05.06) ni se recortan al cerrar (R-1); guardan su tiempo completo. Desde ahí rige RF-05.05 para todos (nadie inicia ▶ ni carga manual), salvo otro reparto del reloj del día. El reparto bloquea el día y luego los proyectos **en orden de id** (como R-2), dentro de una sola transacción.
- **R-5** Toda la aritmética en **segundos enteros**; la UI muestra `h m`.

## Criterios de aceptación

- **CA-05.05** Dado un proyecto con 10 h vendidas y 10 h consumidas, cuando un colaborador da ▶, entonces el timer no arranca, ve el motivo y el botón "pedir ampliación".
- **CA-05.06** Dado un proyecto con 0:30 disponibles y 2 personas cronometrando, cuando pasan 15 minutos, entonces ambos timers se cortan, Consumidas = Bolsa (± 0 s), y ambos reciben notificación.
- **CA-05.03** Dado un timer iniciado en la PC, cuando el usuario abre la app en el celular, entonces ve el mismo timer corriendo con el mismo tiempo.
- **CA-05.04** Dado un proyecto bloqueado, cuando Admin carga +5 h con motivo "Presupuesto #123", entonces Disponibles = 5 h, el bloqueo se levanta y queda una línea en Actividad.
- **CA-05.10** Un timer abierto a las 18:00 y olvidado se detiene solo según P-07 y el usuario ve al día siguiente "Tenés un registro de X h a revisar".
- **CA-05.19a** Dado que no tengo ▶ corriendo ni un día sin repartir, cuando toco "Iniciar día", entonces el header muestra "Día en curso" con el tiempo; dos clics o dos dispositivos dan un solo día.
- **CA-05.19b** Con el día corriendo, el ▶ de cualquier tarea y "Crear e iniciar" se rechazan (`day_clock_running`) sin crear nada; con un ▶ corriendo, "Iniciar día" se rechaza (`timer_running`).
- **CA-05.19c** Al frenar el día se abre "Repartir el día"; si lo cierro sin repartir, el día queda **pendiente**: no puedo iniciar otro día ni un ▶ (`day_split_pending`) hasta repartirlo.
- **CA-05.19d** Dado un día de 09:02 a 18:14, cuando reparto 09:02–11:37 a Playinn y 11:38–15:00 a TOL, entonces se crean 2 registros con esos horarios y comentarios, y lo no repartido no se cuenta en ningún lado.
- **CA-05.19e** Las partes no pueden salirse del día, pisarse entre sí ni pisar otras horas mías (`day_part_overlaps`); cada una lleva proyecto abierto, una tarea **mía** abierta de ese proyecto (o una nueva, que se crea asignada a mí con el código siguiente) y un comentario de 10+ caracteres.
- **CA-05.19f** Dado BH News con 1:30 disponible, cuando reparto 2:29 en BH News, entonces se guarda entero, la bolsa queda en −0:59 y Admin + líder reciben el aviso de bolsa en negativo.
- **CA-05.19g** Dado otro usuario con ▶ corriendo en BH News, cuando mi reparto la deja en negativo, entonces su ▶ sigue corriendo, el corte por bolsa no lo corta y al frenarlo guarda su tiempo completo; después nadie puede iniciar ▶ ni cargar horas manuales en BH News (`no_hours_available`).
- **CA-05.19h** Un día olvidado se corta solo a la medianoche local de la persona (también si lo inició a las 23:50); frenarlo tarde nunca lo estira más allá de esa medianoche.
- **CA-05.19i** "No conté nada este día" (reparto sin partes) cierra el día sin registros; repartir dos veces el mismo día se rechaza (`day_already_split`); un día pendiente se puede repartir aunque tenga más de 7 días.
