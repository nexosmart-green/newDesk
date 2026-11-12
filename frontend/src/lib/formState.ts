/** Form state is plain data (strings, numbers, arrays, nested objects): equal JSON = no changes to lose. */
export function hasChanges(initial: unknown, current: unknown): boolean {
  return JSON.stringify(initial) !== JSON.stringify(current)
}
