import { useEffect, useId, useRef, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { drawSquareCrop, exportSquareCrop, MAX_ZOOM, MIN_ZOOM } from './squareCrop'
import { useUploadMyAvatar } from './useMyProfile'

const PREVIEW_SIZE = 256

export function AvatarCropDialog({ file, onClose }: { file: File; onClose: () => void }) {
  const zoomId = useId()
  const imageRef = useRef<HTMLImageElement | null>(null)
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const [zoom, setZoom] = useState(MIN_ZOOM)
  const [loaded, setLoaded] = useState(false)
  const [localError, setLocalError] = useState<string | null>(null)
  const upload = useUploadMyAvatar()

  useEffect(() => {
    const url = URL.createObjectURL(file)
    const image = new Image()
    image.onload = () => {
      imageRef.current = image
      setLoaded(true)
    }
    // HEIC only decodes on Safari; elsewhere we ask for a common format.
    image.onerror = () => setLocalError('No pudimos leer la imagen. Probá con una foto JPG, PNG o WebP.')
    image.src = url
    return () => URL.revokeObjectURL(url)
  }, [file])

  useEffect(() => {
    if (loaded && imageRef.current && canvasRef.current) drawSquareCrop(canvasRef.current, imageRef.current, zoom)
  }, [loaded, zoom])

  async function handleSave() {
    if (!imageRef.current) return
    const cropped = await exportSquareCrop(imageRef.current, zoom)
    if (!cropped) return setLocalError('Tu navegador no pudo recortar la imagen.')
    upload.mutate({ avatar: cropped.blob, fileName: cropped.fileName }, { onSuccess: onClose })
  }

  return (
    <Dialog title="Recortar foto" description="Ajustá el zoom hasta que la cara quede centrada." onClose={onClose}>
      <div className="flex justify-center">
        <canvas
          ref={canvasRef}
          width={PREVIEW_SIZE}
          height={PREVIEW_SIZE}
          role="img"
          aria-label="Vista previa del recorte"
          className="size-48 rounded-full border border-border bg-bg"
        />
      </div>
      <div className="space-y-1">
        <label htmlFor={zoomId} className="block text-sm text-muted">
          Zoom
        </label>
        <input
          id={zoomId}
          type="range"
          min={MIN_ZOOM}
          max={MAX_ZOOM}
          step={0.05}
          value={zoom}
          disabled={!loaded}
          onChange={(event) => setZoom(Number(event.target.value))}
          className="w-full accent-accent"
        />
      </div>
      {localError && (
        <p role="alert" className="text-sm text-danger">
          {localError}
        </p>
      )}
      <FieldErrorsAlert error={upload.error} />
      <DialogFooter>
        <Button variant="secondary" onClick={onClose}>
          Cancelar
        </Button>
        <Button disabled={!loaded} loading={upload.isPending} onClick={() => void handleSave()}>
          Guardar foto
        </Button>
      </DialogFooter>
    </Dialog>
  )
}
