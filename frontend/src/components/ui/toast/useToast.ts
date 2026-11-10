import { useSyncExternalStore } from 'react'
import { getToasts, subscribeToasts, toast, type Toast } from './toastStore'

/** `const toast = useToast(); toast.success('Guardado.')` — same object as the module-level `toast`. */
export function useToast(): typeof toast {
  return toast
}

export function useToasts(): Toast[] {
  return useSyncExternalStore(subscribeToasts, getToasts, getToasts)
}
