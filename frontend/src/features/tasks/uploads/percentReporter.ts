/** axios `onUploadProgress` → whole percent, ignoring events without a known total. */
export function percentReporter(onProgress: (percent: number) => void) {
  return (event: { loaded: number; total?: number }) => {
    if (event.total) onProgress(Math.round((event.loaded / event.total) * 100))
  }
}
