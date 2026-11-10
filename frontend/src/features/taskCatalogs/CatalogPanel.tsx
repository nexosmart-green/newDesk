import { useId, type ReactNode } from 'react'

/** Shared frame of the task catalog panels in Configuración → Catálogos. */
export function CatalogPanel({ title, description, children }: { title: string; description: string; children: ReactNode }) {
  const headingId = useId()
  return (
    <section aria-labelledby={headingId} className="space-y-3 rounded-lg border border-border bg-surface p-4">
      <header className="space-y-0.5">
        <h2 id={headingId} className="font-semibold">
          {title}
        </h2>
        <p className="text-sm text-muted">{description}</p>
      </header>
      {children}
    </section>
  )
}
