import { ErrorAlert } from './ErrorAlert'

/** Loading / error placeholder shared by pages that fetch a resource or a list. */
export function PageState({ isPending, error }: { isPending: boolean; error: unknown }) {
  if (isPending) return <p className="text-muted">Cargando…</p>
  return <ErrorAlert error={error} showFieldErrors />
}
