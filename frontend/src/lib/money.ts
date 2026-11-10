/** Rates travel as integer cents (no floats for money); the currency comes from the company. */
export function formatMoney(cents: number, currency: string): string {
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency }).format(cents / 100)
}

/** Accepts "1500", "1500,50", "1.500,50" or "1500.50". Returns null when it isn't a non-negative amount. */
export function parseMoneyToCents(input: string): number | null {
  const compact = input.replace(/\s/g, '')
  if (!/^\d[\d.,]*$/.test(compact)) return null
  const normalized = compact.includes(',') ? compact.replace(/\./g, '').replace(',', '.') : compact
  const amount = Number(normalized)
  return Number.isFinite(amount) && amount >= 0 ? Math.round(amount * 100) : null
}
