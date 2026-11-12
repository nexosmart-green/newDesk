import { useRef, useState } from 'react'
import { toast } from '@/components/ui/toast/toastStore'
import { toApiError, type ApiError } from '@/lib/api'
import { toastTextOf } from '@/lib/errorText'

export interface UploadItem {
  id: number
  name: string
  size: number
  percent: number
  /** Set when the file was refused (before sending, or by the backend). */
  error: string | null
  /** Network or server failures may go away; a refused type or size won't. */
  retryable: boolean
}

export type SendUpload<T> = (file: File, onProgress: (percent: number) => void) => Promise<T>

interface UploadQueueOptions<T> {
  /** Same rules as the backend, checked first so a wrong file never waits for a round trip. */
  validate: (file: File) => string | null
  send: SendUpload<T>
  /** Toast title when a file fails. */
  failureTitle: string
  /** Keep failed files listed with their reason (and "Reintentar") instead of only toasting. */
  keepFailures?: boolean
  onUploaded?: (result: T, file: File) => void
}

export interface UploadJob<T> {
  id: number
  /** The result, or null when it failed (already explained). */
  done: Promise<T | null>
}

let nextUploadId = 1

function isRetryable(error: ApiError): boolean {
  return error.status === 0 || error.status >= 500 || error.status === 429
}

/** Files uploading in parallel, each with its own progress bar. Shared by markdown images and attachments. */
export function useUploadQueue<T>({ validate, send, failureTitle, keepFailures = false, onUploaded }: UploadQueueOptions<T>) {
  const [items, setItems] = useState<UploadItem[]>([])
  const files = useRef(new Map<number, File>())

  const patch = (id: number, changes: Partial<UploadItem>) =>
    setItems((current) => current.map((item) => (item.id === id ? { ...item, ...changes } : item)))

  function forget(id: number) {
    files.current.delete(id)
    setItems((current) => current.filter((item) => item.id !== id))
  }

  function fail(id: number, message: string, retryable: boolean) {
    if (keepFailures) patch(id, { error: message, retryable, percent: 0 })
    else forget(id)
  }

  async function run(id: number, file: File): Promise<T | null> {
    try {
      const result = await send(file, (percent) => patch(id, { percent }))
      forget(id)
      onUploaded?.(result, file)
      return result
    } catch (error) {
      const apiError = toApiError(error)
      const text = toastTextOf(apiError)
      toast.error(text.message, { title: failureTitle })
      fail(id, text.message, isRetryable(apiError))
      return null
    }
  }

  /** Starts one upload; null when the file was refused before sending (the reason is shown). */
  function start(file: File): UploadJob<T> | null {
    const id = nextUploadId++
    const problem = validate(file)
    if (problem) {
      if (keepFailures) setItems((current) => [...current, { id, name: file.name, size: file.size, percent: 0, error: problem, retryable: false }])
      else toast.error(problem, { title: failureTitle })
      return null
    }
    files.current.set(id, file)
    setItems((current) => [...current, { id, name: file.name || 'archivo', size: file.size, percent: 0, error: null, retryable: false }])
    return { id, done: run(id, file) }
  }

  function retry(id: number) {
    const file = files.current.get(id)
    if (!file) return
    patch(id, { error: null, retryable: false, percent: 0 })
    void run(id, file)
  }

  const inFlight = items.filter((item) => item.error === null).length
  return { items, start, retry, dismiss: forget, inFlight }
}
