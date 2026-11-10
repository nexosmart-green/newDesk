export type CsvCell = string | number | null

/** `;`-separated (what Excel expects with a Spanish locale), quoted when needed, CRLF rows. */
export function toCsv(rows: readonly (readonly CsvCell[])[]): string {
  const cell = (value: CsvCell) => {
    const text = value === null ? '' : String(value)
    return /[";\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text
  }
  return rows.map((row) => row.map(cell).join(';')).join('\r\n')
}

/** Saves `csv` as a file in the browser. The BOM makes Excel read the accents as UTF-8. */
export function downloadCsv(filename: string, csv: string): void {
  downloadBlob(new Blob(['﻿', csv], { type: 'text/csv;charset=utf-8' }), filename)
}

/** Hands a blob (built here or downloaded from the API) to the browser as a file. */
export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}
