# Preguntas abiertas para el PO

🔴 = bloquea el arranque de R1 · 🟡 = bloquea un módulo puntual · ⚪ = tiene default, se puede cambiar después · ✅ = respondida

Cada una trae **la recomendación del equipo**. Si está bien, alcanza con "OK".

## Respondidas (2026-11-06)

| ID | Pregunta | Respuesta / decisión |
|---|---|---|
| P-02 | Roles | ✅ **Roles editables con permisos granulares** (el dueño reparte permisos, ej. cargar horas). |
| P-01 | ¿Reescribir o extender Worksuite? | ✅ **Proyecto nuevo, Worksuite se descarta.** ADR-0001 aceptado. |
| P-04 | ¿El colaborador crea tareas? | ✅ **Sí, crea y asigna tareas** (hoy ya lo hace). Asigna a miembros del proyecto. |
| P-05 | Modelos de venta de horas | ✅ **No hay facturación.** El dueño, o quien tenga el permiso, carga horas al proyecto a mano. Un solo tipo de bolsa (+ proyecto Interno sin tope). Sin abono mensual. |
| P-06 | Horas manuales | ✅ "Da lo mismo" → **default**: permitidas hasta 7 días atrás sin aprobación; más atrás, solo quien tenga permiso de gestionar horas. Configurable. |
| P-10 | País / moneda / TZ | ✅ **Configurable por empresa**, default **ARS** y `America/Argentina/Buenos_Aires`. |
| P-13 | Cerradura | ✅ **Nexxt Solutions Smart Wi-Fi Door Lock, deadbolt negro** (probable NHS-D150 BK). App Nexxt Home (plataforma Tuya). Ver `integraciones/cerradura`. |
| P-17 | ¿Precio vendido por bolsa? | ✅ No (sin facturación). Queda campo "referencia" opcional en texto. |
| P-18 / P-19 | Migración y acceso a la DB | ✅ **Después del proyecto.** R1 arranca con carga inicial limpia; migración pasa a épica post-R1. |
| P-20 | Horas vendidas por proyecto | ✅ OK, el dueño las carga al dar de alta los proyectos. |

## Pendientes

| ID | | Pregunta | Recomendación / default |
|---|---|---|---|
| P-03 | ⚪ | ¿Los clientes entran al sistema? | No en R1. |
| P-07 | ⚪ | Timer olvidado: ¿cuándo cortar? | Fin del turno + 1 h; sin turno, a las 10 h. |
| P-08 | ⚪ | Áreas | Dev, Diseño, Marketing, CM, Administración. |
| P-09 | ⚪ | Slack / WhatsApp | Quitar Slack; WhatsApp en R3. |
| P-11 | ⚪ | Preaviso | Quitar. |
| P-12 | ⚪ | CUIL y contacto de emergencia | Sí, solo visibles para Admin. |
| P-14 | ⚪ | Tolerancia de llegada tarde | 10 minutos. |
| P-15 | 🟡 | ¿Hay gente **remota**? | Primer timer del día = inicio de jornada. |
| P-16 | ⚪ | Cupos de vacaciones | Definidos por la empresa. |
| P-21 | ⚪ | ¿Varios asignados por tarea? | Sí; cada uno cronometra lo suyo. |
| P-22 | ⚪ | Dominio | `desk.nexosmart.es` al salir; staging aparte. |
| P-23 | 🟡 | **"Configurable por empresa"**: ¿newDesk lo va a usar **más de una empresa** (multi-empresa / SaaS) o solo NexoSmart con su configuración? | Solo NexoSmart, pero con tabla `companies` y `company_id` desde el día 1 para no cerrar la puerta. |
| P-24 | 🟡 | Cerradura: ¿la salida se abre girando la perilla de adentro? ¿Quién tiene la cuenta admin de Nexxt Home? ¿Confirmamos modelo con foto de la etiqueta? | — Ver G-25: la cerradura registra **aperturas desde afuera**, la salida suele no quedar registrada a nombre de nadie. |
| P-25 | ⚪ | RF-01.05 pide fotos **HEIC**. GD (PHP) no decodifica HEIC; hace falta Imagick + libheif en el servidor de prod. ¿Instalamos Imagick en prod o alcanza con que iPhone suba JPG? | Hoy: JPG/PNG/WebP con mensaje claro para iPhone ("Cámara → Formatos → Más compatible"); Safari ya convierte HEIC→JPEG al subir en la mayoría de los casos. El procesador está detrás de la interfaz `AvatarProcessor`: agregar HEIC = un adaptador Imagick, sin tocar el dominio. |
| P-26 | ⚪ | RF-06.06 "Feriados nacionales (AR) cargados": ¿de qué fuente oficial los tomamos? Los trasladables cambian cada año por decreto. | Hoy se cargan desde la app (Admin → feriados, alcance nacional/empresa). Propuesta: importar al inicio de cada año desde la API pública ArgentinaDatos o el calendario oficial de argentina.gob.ar, con revisión manual. |
