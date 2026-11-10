import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { expiryOptions, type IssuedToken, type TokenExpiry } from './types'
import { useCreateAiToken } from './useAiConnections'

const NO_EXPIRY = 'never'

function expiryOf(value: string): TokenExpiry {
  return value === '30' || value === '90' || value === '365' ? (Number(value) as TokenExpiry) : null
}

interface CreateTokenDialogProps {
  onIssued: (issued: IssuedToken) => void
  onClose: () => void
}

/** RF-12.02: name + optional expiry. The secret comes back once and is handed to `onIssued`. */
export function CreateTokenDialog({ onIssued, onClose }: CreateTokenDialogProps) {
  const create = useCreateAiToken()
  const [name, setName] = useState('')
  const [expiry, setExpiry] = useState('90')

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!name.trim()) return
    create.mutate({ name: name.trim(), expires_in_days: expiryOf(expiry) }, { onSuccess: onIssued })
  }

  return (
    <Dialog title="Generar token" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <TextField
          label="Nombre"
          required
          maxLength={60}
          placeholder="Claude Code en mi notebook"
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={fieldErrorOf(create.error, 'name')}
        />
        <SelectField label="Vencimiento" value={expiry} onChange={(event) => setExpiry(event.target.value)}>
          {expiryOptions.map((option) => (
            <option key={option.label} value={option.value === null ? NO_EXPIRY : String(option.value)}>
              {option.label}
            </option>
          ))}
        </SelectField>
        <ErrorAlert error={create.error} />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={create.isPending || !name.trim()}>
            {create.isPending ? 'Generando…' : 'Generar'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
