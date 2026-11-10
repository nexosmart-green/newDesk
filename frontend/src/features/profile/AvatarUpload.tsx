import { useId, useState, type ChangeEvent } from 'react'
import { Avatar } from '@/components/ui/Avatar'
import type { PersonProfile } from '@/features/people/types'
import { AvatarCropDialog } from './AvatarCropDialog'

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif']
const MAX_BYTES = 5 * 1024 * 1024

/** RF-01.05: pick a photo, crop it to a square here, upload the crop (backend makes the 64/256px sizes). */
export function AvatarUpload({ profile }: { profile: PersonProfile }) {
  const hintId = useId()
  const [file, setFile] = useState<File | null>(null)
  const [localError, setLocalError] = useState<string | null>(null)

  function handleFile(event: ChangeEvent<HTMLInputElement>) {
    const chosen = event.target.files?.[0]
    event.target.value = ''
    if (!chosen) return
    if (!ACCEPTED_TYPES.includes(chosen.type)) return setLocalError('La foto tiene que ser JPG, PNG, WebP o HEIC.')
    if (chosen.size > MAX_BYTES) return setLocalError('La foto no puede pesar más de 5 MB.')
    setLocalError(null)
    setFile(chosen)
  }

  return (
    <div className="flex items-center gap-3">
      <Avatar name={profile.full_name} url={profile.avatar?.profile} size="lg" />
      <div className="space-y-1">
        <label className="relative inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-surface-2 focus-within:outline-2 focus-within:outline-accent">
          Cambiar foto
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.heic,.heif"
            aria-describedby={hintId}
            onChange={handleFile}
            className="sr-only"
          />
        </label>
        <p id={hintId} className="text-xs text-muted">
          JPG, PNG, WebP o HEIC, hasta 5 MB.
        </p>
        {localError && (
          <p role="alert" className="text-sm text-danger">
            {localError}
          </p>
        )}
      </div>
      {file && <AvatarCropDialog file={file} onClose={() => setFile(null)} />}
    </div>
  )
}
