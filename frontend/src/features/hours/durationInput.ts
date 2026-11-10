/**
 * R-5: hours typed by a person ("2,5", "2.5", "-1,25") → integer seconds. Comma or dot as decimal
 * separator; `null` when it isn't a number. Minutes are never asked separately.
 */
export function hoursToSeconds(text: string): number | null {
  const normalized = text.trim().replace(',', '.')
  if (!/^-?(\d+(\.\d*)?|\.\d+)$/.test(normalized)) return null
  return Math.round(Number(normalized) * 3600)
}
