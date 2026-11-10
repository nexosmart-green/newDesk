/**
 * localStorage that never throws: private windows, blocked site data or a full quota just mean
 * "nothing stored". Callers validate what comes back — stored JSON is untrusted.
 */
export function readStoredJson(key: string): unknown {
  try {
    const raw = window.localStorage.getItem(key)
    return raw === null ? null : (JSON.parse(raw) as unknown)
  } catch {
    return null
  }
}

export function writeStoredJson(key: string, value: unknown): void {
  try {
    window.localStorage.setItem(key, JSON.stringify(value))
  } catch {
    // Storage unavailable: the preference simply isn't remembered.
  }
}
