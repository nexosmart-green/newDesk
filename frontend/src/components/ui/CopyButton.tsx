import { useState } from 'react'
import { Button } from './Button'

interface CopyButtonProps {
  value: string
  /** Accessible name, e.g. "Copiar token". */
  label: string
}

/** Copies `value` to the clipboard and confirms it in place (announced to screen readers). */
export function CopyButton({ value, label }: CopyButtonProps) {
  const [state, setState] = useState<'idle' | 'copied' | 'failed'>('idle')

  async function copy() {
    try {
      await navigator.clipboard.writeText(value)
      setState('copied')
    } catch {
      setState('failed')
    }
  }

  return (
    <Button variant="secondary" aria-label={label} onClick={() => void copy()} className="shrink-0">
      <span aria-live="polite">{state === 'copied' ? 'Copiado' : state === 'failed' ? 'Copialo a mano' : 'Copiar'}</span>
    </Button>
  )
}

/** A secret, URL or command shown in monospace with its copy button. */
export function CopyableValue({ value, label }: CopyButtonProps) {
  return (
    <div className="flex items-start gap-2">
      <pre className="min-w-0 flex-1 overflow-x-auto whitespace-pre-wrap break-all rounded border border-border bg-bg p-2 font-mono text-xs">
        {value}
      </pre>
      <CopyButton value={value} label={label} />
    </div>
  )
}
