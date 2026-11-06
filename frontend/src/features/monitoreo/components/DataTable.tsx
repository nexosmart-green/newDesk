import type { ReactNode } from 'react'
import { formatNumber } from '../lib/format'
import { usePage } from '../lib/paginate'
import { Icon } from './Icon'
import { Empty, Spinner, TableWrap } from './Panel'

export interface Column<T> {
  header: string
  cell: (row: T) => ReactNode
  align?: 'right'
  className?: string
}

interface DataTableProps<T> {
  rows: readonly T[]
  columns: Column<T>[]
  rowKey: (row: T, index: number) => string
  empty?: ReactNode
  rowClassName?: (row: T) => string
  caption?: string
  loading?: boolean
  noun?: string
}

const skeletonRows = [0, 1, 2, 3, 4]

// Pagina en el navegador con la regla de lib/paginate. Las listas que pagina el
// servidor (alertas, usuarios, sesiones) llegan de a 10 y no la activan.
export function DataTable<T>({ rows, columns, rowKey, empty = 'Sin datos.', rowClassName, caption, loading = false, noun = 'filas' }: DataTableProps<T>) {
  const page = usePage(rows)
  if (!loading && rows.length === 0) return <Empty>{empty}</Empty>
  return (
    <>
      <TableWrap>
        <table className="table" aria-busy={loading || undefined}>
          {caption && <caption className="sr-only">{caption}</caption>}
          <thead>
            <tr>
              {columns.map((column) => (
                <th key={column.header} className={column.align === 'right' ? 'text-right' : undefined}>
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody key={page.page} className={page.pages > 1 ? 'fade-in' : undefined}>
            {loading
              ? skeletonRows.map((row) => (
                  <tr key={row} aria-hidden="true">
                    {columns.map((column, col) => (
                      <td key={column.header}>
                        <span className="skeleton h-3.5" style={{ width: `${40 + ((row * 7 + col * 13) % 45)}%` }} />
                      </td>
                    ))}
                  </tr>
                ))
              : page.rows.map((row, index) => (
                  <tr key={rowKey(row, index)} className={rowClassName?.(row)}>
                    {columns.map((column) => (
                      <td key={column.header} className={`${column.align === 'right' ? 'text-right' : ''} ${column.className ?? ''}`}>
                        {column.cell(row)}
                      </td>
                    ))}
                  </tr>
                ))}
          </tbody>
        </table>
      </TableWrap>
      {!loading && <Pagination page={page.page} pages={page.pages} total={page.total} noun={noun} onChange={page.setPage} />}
    </>
  )
}

interface PaginationProps {
  page: number
  pages: number
  total: number
  noun: string
  onChange: (page: number) => void
  busy?: boolean
}

// Barra común de paginación (tablas del navegador y listas del servidor). Con una sola página no se muestra.
export function Pagination({ page, pages, total, noun, onChange, busy = false }: PaginationProps) {
  if (pages <= 1) return null
  return (
    <nav aria-label={`Paginación de ${noun}`} className="flex items-center justify-between gap-3 border-t border-line px-5 py-3">
      <button type="button" className="btn btn-sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>
        <Icon name="chevron" size={12} className="rotate-180" />
        Anterior
      </button>
      <span className="num flex items-center gap-2 text-xs text-muted">
        {busy && <Spinner />}
        Página {page} de {pages} · {formatNumber(total)} {noun}
      </span>
      <button type="button" className="btn btn-sm" disabled={page >= pages} onClick={() => onChange(page + 1)}>
        Siguiente
        <Icon name="chevron" size={12} />
      </button>
    </nav>
  )
}
