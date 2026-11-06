# RNF — Requerimientos no funcionales

| ID | Requerimiento |
|---|---|
| RNF-01 | **Responsive / mobile first** en Mis tareas, cronómetro y Mi calendario (se usan desde el celular). PWA instalable (S). |
| RNF-02 | Modo oscuro y claro (hoy es oscuro y el equipo está acostumbrado). |
| RNF-03 | Español por defecto; i18n preparado (hay perfiles en España: dominio `.es`, tarifas en €). |
| RNF-04 | Zona horaria y moneda por empresa (default `America/Argentina/Buenos_Aires` y ARS), override de TZ por usuario. Guardar todo en UTC. Cronómetro y cortes en hora del servidor, nunca del navegador. |
| RNF-05 | Seguridad: HTTPS forzado, sin headers que expongan stack, sin "Default Company" ni nombre de framework en respuestas públicas, rate limit en login, 2FA para Admin, sesiones revocables, autorización por **policies** en backend (la UI que oculta botones no es seguridad). |
| RNF-06 | Datos personales: minimización (no pedir estado civil ni aniversario de matrimonio), tarifa y datos sensibles visibles solo para Admin, exportar/borrar datos de una persona a pedido (Ley 25.326 / RGPD). |
| RNF-07 | Auditoría: toda modificación de horas, bolsas, tarifas, roles y fichajes queda registrada (quién, cuándo, antes/después). |
| RNF-08 | Rendimiento: listados < 500 ms p95 con 10k tareas; acción del cronómetro < 300 ms. |
| RNF-09 | Disponibilidad: backups diarios de DB con retención 30 días y **restore probado**. |
| RNF-10 | Observabilidad: endpoints `/health` y `/errors` según el contrato de `/bug-report` (obligatorio en todos los proyectos NexoSmart), canal de log `errors`, `ERRORS_TOKEN`. |
| RNF-11 | Procesos en segundo plano (cortes de timer, alertas, ingesta de cerradura, emails) como programas de **Supervisord**, nunca crontab. |
| RNF-12 | Tiempo real: el cronómetro del header y el corte por horas se reflejan en todas las pestañas/dispositivos abiertos del usuario sin recargar (WebSocket o polling corto). |
| RNF-13 | Accesibilidad AA en contraste y navegación por teclado (el rojo de "vencida" de hoy no pasa contraste sobre gris oscuro). |
