import { useEffect, useState } from 'react'

type Theme = 'dark' | 'light'
const storageKey = 'newdesk.theme'

function readStoredTheme(): Theme {
  try {
    return localStorage.getItem(storageKey) === 'light' ? 'light' : 'dark'
  } catch {
    return 'dark'
  }
}

export function useTheme() {
  const [theme, setTheme] = useState<Theme>(readStoredTheme)

  useEffect(() => {
    document.documentElement.classList.toggle('light', theme === 'light')
    document.documentElement.classList.toggle('dark', theme === 'dark')
    try {
      localStorage.setItem(storageKey, theme)
    } catch {
      // Storage may be unavailable (private mode); the theme still applies for this session.
    }
  }, [theme])

  return { theme, toggleTheme: () => setTheme((current) => (current === 'dark' ? 'light' : 'dark')) }
}
