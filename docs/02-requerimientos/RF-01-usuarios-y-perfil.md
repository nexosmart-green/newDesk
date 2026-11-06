# RF-01 Usuarios, roles y perfil

| ID | Requerimiento | Prio |
|---|---|---|
| RF-01.01 | Alta de usuario por Admin con invitación por email (el usuario define su contraseña). No hay registro público. | M |
| RF-01.02 | Login email + contraseña; recuperación de contraseña; 2FA opcional (obligatorio para Admin). | M / S (2FA) |
| RF-01.03 | Roles editables compuestos por permisos granulares (`projects.manage`, `hours.allocate`, `tasks.assign`, `shifts.manage`, `rates.view`…). Roles base: Admin, Gestor, Colaborador. Un usuario tiene un rol. P-02 ✅ | M |
| RF-01.04 | Perfil con los campos definidos en `docs/01-relevamiento` (columna "Decisión"). Nombre y apellido separados. | M |
| RF-01.05 | Foto de perfil: subir, recortar a cuadrado, guardar en 2 tamaños (avatar 64px, perfil 256px). JPG/PNG/WebP/HEIC ≤ 5 MB. | M |
| RF-01.06 | Habilidades estructuradas: catálogo (PHP, Laravel, Figma, Meta Ads…) + nivel 0–100. Buscable ("¿quién sabe React?"). | S |
| RF-01.07 | "Reporta a" (un superior) → organigrama simple y "mi equipo". | S |
| RF-01.08 | Catálogos administrables: Puestos, Áreas, Tipos de empleo. | M |
| RF-01.09 | Qué edita cada uno: el colaborador edita contacto, foto, bio, habilidades, idioma. Admin edita puesto, área, ingreso/egreso, tarifa, tipo de empleo, rol. | M |
| RF-01.10 | Baja de usuario = desactivar (no borrar): conserva horas y autoría; no puede loguearse; desaparece de selectores de asignación. | M |
| RF-01.11 | Tarifa por hora con **historial con vigencia** (desde/hasta), para que un aumento no reescriba la rentabilidad pasada. En la moneda de la empresa (default ARS). Solo Admin. | C |

## Criterios de aceptación

- **CA-01.10** Dado un usuario con horas en 3 proyectos, cuando Admin lo da de baja, entonces sus horas siguen sumando en los proyectos y su nombre aparece en el historial, pero no en "Asignar a". Si tenía un timer corriendo, se detiene en ese momento.
- **CA-01.11** Dado que la tarifa cambió de 10 a 12 el 01/10, cuando se reporta septiembre, entonces se usa 10.
- **CA-01.09** Un colaborador que intenta modificar su tarifa por API recibe 403.
