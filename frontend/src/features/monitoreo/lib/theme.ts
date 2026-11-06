import { useSyncExternalStore } from 'react'

// El módulo sigue el claro/oscuro de newDesk (RF-13, diferencia 3): useTheme pone .light o .dark en <html>.
const root = () => document.documentElement

function subscribe(onChange: () => void) {
  const observer = new MutationObserver(onChange)
  observer.observe(root(), { attributes: true, attributeFilter: ['class'] })
  return () => observer.disconnect()
}

const currentScheme = (): 'light' | 'dark' => (root().classList.contains('light') ? 'light' : 'dark')

// Esquema efectivo. Lo usan los gráficos, que pintan en canvas.
export function useColorScheme(): 'light' | 'dark' {
  return useSyncExternalStore(subscribe, currentScheme)
}
