import { useState } from 'react'
import { Button } from '@/components/ui/Button'

interface RecoveryCodesProps {
  codes: string[]
  onAcknowledge: () => void
}

/** Shown exactly once, right after enabling 2FA: the backend never returns them again. */
export function RecoveryCodes({ codes, onAcknowledge }: RecoveryCodesProps) {
  const [copied, setCopied] = useState(false)
  const [saved, setSaved] = useState(false)

  async function copyCodes() {
    try {
      await navigator.clipboard.writeText(codes.join('\n'))
      setCopied(true)
    } catch {
      setCopied(false)
    }
  }

  return (
    <section aria-labelledby="recovery-codes-title" className="space-y-4">
      <h2 id="recovery-codes-title" className="text-lg font-semibold">
        Códigos de recuperación
      </h2>
      <p className="rounded border border-warning/40 bg-warning/10 px-3 py-2 text-sm text-warning">
        Guardalos en un lugar seguro. Cada código sirve una sola vez si perdés el celular, y no se vuelven a mostrar.
      </p>
      <ul aria-label="Códigos de recuperación" className="grid grid-cols-2 gap-2 font-mono text-sm">
        {codes.map((code) => (
          <li key={code} className="rounded border border-border bg-bg px-2 py-1 text-center">
            {code}
          </li>
        ))}
      </ul>
      <Button variant="secondary" onClick={copyCodes}>
        {copied ? 'Copiados' : 'Copiar códigos'}
      </Button>
      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" checked={saved} onChange={(event) => setSaved(event.target.checked)} />
        Ya guardé los códigos
      </label>
      <Button disabled={!saved} onClick={onAcknowledge}>
        Listo
      </Button>
    </section>
  )
}
