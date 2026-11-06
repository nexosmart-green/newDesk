# RF-11 Configuración de empresa

Origen: P-10 ("configurable por empresa, pero debería estar en ARS"). ⚠P-23: ¿una sola empresa o varias?

| ID | Requerimiento | Prio |
|---|---|---|
| RF-11.01 | Datos de la empresa: nombre (el que se ve en el título y los emails — nunca "Default Company"), logo, CUIT. | M |
| RF-11.02 | Moneda (default **ARS**) y zona horaria (default `America/Argentina/Buenos_Aires`). | M |
| RF-11.03 | Reglas de horas: días hacia atrás permitidos para carga manual (default 7), corte de timer olvidado (default fin de turno + 1 h / 10 h), umbrales de alerta (80% / 100%). | M |
| RF-11.04 | Estados de tarea (RF-04.03), catálogos de Puestos / Áreas / Tipos de empleo, roles y permisos (RF-01.03). | M |
| RF-11.05 | Asistencia: tolerancia de llegada tarde (default 10 min), feriados, credenciales de la cerradura (cifradas). | S (R2) |

- **CA-11.03** Cambiar los días permitidos de 7 a 3 aplica a cargas nuevas; no invalida registros ya cargados.
- Todas las tablas de negocio llevan `company_id` desde el inicio, aunque haya una sola empresa.
