/**
 * App-wide toasts: a tiny external store so anything can raise one — components, mutation hooks
 * (whose callbacks outlive a closed dialog) and the React Query caches — without a provider in the way.
 */
export type ToastVariant = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  variant: ToastVariant
  message: string
  title?: string
  /** Milliseconds before it leaves by itself. */
  duration: number
}

export interface ToastOptions {
  title?: string
  duration?: number
}

export const TOAST_DURATION_MS = 4000
const MAX_VISIBLE = 4

let toasts: Toast[] = []
let nextId = 1
const listeners = new Set<() => void>()

function emit(next: Toast[]) {
  toasts = next
  listeners.forEach((listener) => listener())
}

function show(variant: ToastVariant, message: string, options: ToastOptions = {}): number {
  // The same message twice in a row (double click, two failing queries) shows once.
  const duplicate = toasts.find((item) => item.variant === variant && item.message === message && item.title === options.title)
  if (duplicate) return duplicate.id
  const item: Toast = { id: nextId++, variant, message, title: options.title, duration: options.duration ?? TOAST_DURATION_MS }
  emit([...toasts, item].slice(-MAX_VISIBLE))
  return item.id
}

export const toast = {
  success: (message: string, options?: ToastOptions) => show('success', message, options),
  error: (message: string, options?: ToastOptions) => show('error', message, options),
  info: (message: string, options?: ToastOptions) => show('info', message, options),
  dismiss: (id: number) => emit(toasts.filter((item) => item.id !== id)),
  clear: () => emit([]),
}

export function subscribeToasts(listener: () => void): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export function getToasts(): Toast[] {
  return toasts
}
