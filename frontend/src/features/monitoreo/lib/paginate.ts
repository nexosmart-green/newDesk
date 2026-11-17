import { useState } from 'react'
import type { PageMeta } from '../api/types'

// Misma regla que el backend (internal/panel/store/page.go): páginas de 10, pero con
// menos de 20 filas va todo en una página. La página pedida se ajusta a 1..última.
// perPage fijo para listas que no siguen esa regla (incidentes: de a 5).
export const pagesOf = ({ total, per_page }: Pick<PageMeta, 'total' | 'per_page'>) => Math.max(1, Math.ceil(total / per_page))

export function paginate<T>(rows: readonly T[], requested: number, perPage?: number) {
  const total = rows.length
  const per_page = perPage ?? (total < 20 ? 20 : 10)
  const pages = pagesOf({ total, per_page })
  const page = Math.min(Math.max(1, Math.trunc(requested) || 1), pages)
  return { rows: rows.slice((page - 1) * per_page, page * per_page), page, pages, per_page, total }
}

// Paginación en el navegador para listas que llegan enteras (secciones del agente).
// El ajuste se calcula en cada render: si la lista se achica, se ve la última página.
export function usePage<T>(rows: readonly T[], perPage?: number) {
  const [requested, setPage] = useState(1)
  return { ...paginate(rows, requested, perPage), setPage }
}
