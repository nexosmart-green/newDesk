# Inbox de requerimientos

Todo pedido nuevo entra acá **tal cual llegó**, sin interpretar. Después el analista lo procesa.

## Cómo cargar uno

Un archivo por pedido: `AAAA-MM-DD-tema-corto.md` (capturas al lado, mismo prefijo).

```markdown
# <título corto>
- Pidió: <quién>
- Fecha: AAAA-MM-DD
- Prioridad que le da quien pide: alta | media | baja

## Pedido original
<texto literal, pegado sin editar>

## Adjuntos
- <captura / link>
```

## Cómo se procesa

1. Se lee y se asigna a un módulo (`RF-xx`) o se crea uno nuevo.
2. Si genera dudas → van a `docs/03-gaps/preguntas-abiertas.md`.
3. Si contradice algo existente → se anota en `docs/03-gaps/gap-analysis.md`.
4. El archivo se mueve a `procesados/` con una línea al final: `→ Absorbido en RF-xx (RF-xx.yy)`.

Nada se pierde: el inbox procesado es la trazabilidad pedido → requerimiento.

## Procesados

- `procesados/2026-11-06-kickoff.md` — pedido inicial (→ RF-01 a RF-09).
