/** Tareas (E3) and Horas (E4) tabs exist from day one so the layout doesn't move when they land. */
export function ComingSoonTab({ title }: { title: string }) {
  return (
    <div className="rounded-lg border border-dashed border-border p-6 text-center">
      <h2 className="font-semibold">{title}</h2>
      <p className="text-sm text-muted">Disponible en la próxima entrega.</p>
    </div>
  )
}
