import type { Ref } from 'react'
import { ACCEPT_ATTRIBUTE } from './useImageUploads'

/** The hidden picker behind "Insertar imagen". The toolbar button is the accessible control. */
export function ImageFileInput({ inputRef, onFiles }: { inputRef: Ref<HTMLInputElement>; onFiles: (files: File[]) => void }) {
  return (
    <input
      ref={inputRef}
      type="file"
      accept={ACCEPT_ATTRIBUTE}
      multiple
      hidden
      aria-hidden="true"
      tabIndex={-1}
      onChange={(event) => {
        onFiles(Array.from(event.target.files ?? []))
        event.target.value = ''
      }}
    />
  )
}
