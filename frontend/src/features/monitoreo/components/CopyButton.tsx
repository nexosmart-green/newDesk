import { useEffect, useState, type MouseEvent } from 'react'
import { copyText } from '../lib/clipboard'
import { Icon } from './Icon'

interface CopyProps {
  text: string
  label?: string
  title?: string
  variant?: 'chip' | 'button'
}

// Ícono copiar que se transforma en check (CSS .swap).
function SwapIcon({ copied }: { copied: boolean }) {
  return (
    <span className="swap" data-on={copied || undefined}>
      <Icon name="copy" size={12} />
      <Icon name="check" size={12} className="text-ok" />
    </span>
  )
}

// Copia al portapapeles con feedback de 1,5 s. `chip` muestra el texto en mono.
export function CopyButton({ text, label, title, variant = 'button' }: CopyProps) {
  const [copied, setCopied] = useState(false)

  useEffect(() => {
    if (!copied) return
    const timer = window.setTimeout(() => setCopied(false), 1500)
    return () => window.clearTimeout(timer)
  }, [copied])

  const onClick = async (event: MouseEvent) => {
    event.preventDefault()
    event.stopPropagation()
    if (await copyText(text)) setCopied(true)
  }

  if (variant === 'chip') {
    return (
      <button
        type="button"
        onClick={onClick}
        title={title ?? `Copiar ${text}`}
        className="inline-flex h-6 max-w-full items-center gap-1.5 rounded-md bg-surface-2 px-2 font-mono text-xs text-text transition hover:bg-surface-3 active:scale-[0.97]"
      >
        <span className="truncate">{label ?? text}</span>
        <span className="text-muted">
          <SwapIcon copied={copied} />
        </span>
        <span className="sr-only" aria-live="polite">
          {copied ? 'Copiado' : ''}
        </span>
      </button>
    )
  }

  return (
    <button type="button" onClick={onClick} className="btn btn-sm" title={title ?? `Copiar ${text}`}>
      <SwapIcon copied={copied} />
      <span aria-live="polite">{copied ? 'Copiado' : (label ?? 'Copiar')}</span>
    </button>
  )
}
