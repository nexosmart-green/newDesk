import { useId, useState, type ClipboardEvent, type FormEvent } from 'react'
import { ApiError } from '@/lib/api'
import { errorMessage } from '../api/monitoringApi'
import { Icon } from '../components/Icon'
import { ErrorNote, Spinner } from '../components/Panel'
import { MESSAGE_IMAGES_MAX, MESSAGE_MAX, screenshotProblem, SCREENSHOT_TYPES } from '../lib/reports'
import { useDataUrl } from '../lib/useDataUrl'
import type { ReportThreadProps } from './ReportThread'

// `canNote`: el equipo puede dejar notas internas, que el cliente no ve.
export function Composer({ send, canNote }: { send: ReportThreadProps['send']; canNote: boolean }) {
  const [body, setBody] = useState('')
  const [images, setImages] = useState<File[]>([])
  const [internal, setInternal] = useState(false)
  const [localErrors, setLocalErrors] = useState<{ body?: string; images?: string }>({})
  const ids = { body: useId(), file: useId() }
  const apiError = send.error instanceof ApiError ? send.error : null
  const errorFor = (field: 'body' | 'images') => localErrors[field] ?? apiError?.field(field)

  const attach = (files: File[]) => {
    let next = images
    let problem: string | undefined
    for (const file of files) {
      const issue = screenshotProblem(file, 'La imagen') ?? (next.length >= MESSAGE_IMAGES_MAX ? `Hasta ${MESSAGE_IMAGES_MAX} imágenes por mensaje.` : null)
      if (issue) problem = issue
      else next = [...next, file]
    }
    setImages(next)
    setLocalErrors({ images: problem })
  }

  // Pegar una captura (Ctrl+V / Cmd+V) la adjunta, como en el reporte.
  const onPaste = (event: ClipboardEvent) => {
    const pasted = [...event.clipboardData.files].filter((file) => file.type.startsWith('image/'))
    if (pasted.length > 0) {
      event.preventDefault()
      attach(pasted)
    }
  }

  const submit = (event: FormEvent) => {
    event.preventDefault()
    if (!body.trim() && images.length === 0) {
      setLocalErrors({ body: 'Escribí un mensaje o adjuntá una imagen.' })
      return
    }
    const form = new FormData()
    form.append('body', body.trim())
    // images[]: PHP solo arma la lista con corchetes (con 'images' repetido monitoreo leía todas; Laravel, la última).
    for (const image of images) form.append('images[]', image, image.name)
    if (internal) form.append('internal', '1')
    send.mutate(form, {
      onSuccess: () => {
        setBody('')
        setImages([])
        setInternal(false)
        setLocalErrors({})
      },
    })
  }

  return (
    <form onSubmit={submit} onPaste={onPaste} noValidate aria-label="Escribir un mensaje" className="space-y-3 border-t border-line pt-4">
      <fieldset disabled={send.isPending} className="min-w-0 space-y-3">
        <div>
          <label htmlFor={ids.body} className="label mb-1.5 block">Mensaje</label>
          <textarea
            id={ids.body}
            rows={3}
            className="input h-auto min-h-20 py-2.5 leading-relaxed"
            placeholder="Escribí tu mensaje. Podés pegar una captura con Ctrl+V."
            value={body}
            maxLength={MESSAGE_MAX}
            aria-invalid={Boolean(errorFor('body')) || undefined}
            onChange={(event) => {
              setBody(event.target.value)
              setLocalErrors((current) => ({ ...current, body: undefined }))
            }}
          />
          {errorFor('body') && <span role="alert" className="mt-1.5 block text-xs text-crit">{errorFor('body')}</span>}
        </div>

        {images.length > 0 && (
          <ul className="flex flex-wrap gap-2" aria-label="Imágenes adjuntas">
            {images.map((image, index) => (
              <Preview key={`${image.name}-${index}`} file={image} onRemove={() => setImages((current) => current.filter((each) => each !== image))} />
            ))}
          </ul>
        )}
        {errorFor('images') && <span role="alert" className="block text-xs text-crit">{errorFor('images')}</span>}

        {canNote && (
          <label className="flex w-fit cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" className="size-4" checked={internal} onChange={(event) => setInternal(event.target.checked)} />
            Nota interna (el cliente no la ve)
          </label>
        )}

        <div className="flex flex-wrap items-center gap-3">
          <label htmlFor={ids.file} className={`btn btn-sm ${images.length >= MESSAGE_IMAGES_MAX ? 'pointer-events-none opacity-50' : 'cursor-pointer'}`}>
            <Icon name="image" size={14} />
            Adjuntar imagen
          </label>
          <input
            id={ids.file}
            type="file"
            multiple
            accept={SCREENSHOT_TYPES.join(',')}
            className="sr-only"
            aria-label="Imágenes del mensaje"
            onChange={(event) => {
              attach([...(event.target.files ?? [])])
              event.target.value = ''
            }}
          />
          <span className="text-xs text-muted">Hasta {MESSAGE_IMAGES_MAX} imágenes PNG, JPG o WebP de 5 MB.</span>
          <button type="submit" className="btn btn-primary btn-sm ml-auto">
            {send.isPending ? <Spinner /> : <Icon name="check" size={14} />}
            Enviar
          </button>
        </div>
      </fieldset>
      {apiError && Object.keys(apiError.fieldErrors).length === 0 && <ErrorNote>{apiError.message}</ErrorNote>}
      {send.isError && !apiError && <ErrorNote>{errorMessage(send.error)}</ErrorNote>}
    </form>
  )
}

function Preview({ file, onRemove }: { file: File; onRemove: () => void }) {
  const url = useDataUrl(file)
  return (
    <li className="relative">
      {url ? <img src={url} alt={`Adjunta: ${file.name}`} className="h-20 w-28 rounded-lg object-cover" /> : <div className="h-20 w-28 rounded-lg bg-surface-2" />}
      <button type="button" className="btn btn-sm absolute top-1 right-1 h-6 px-1.5 text-xs" onClick={onRemove} aria-label={`Quitar ${file.name}`}>
        Quitar
      </button>
    </li>
  )
}
