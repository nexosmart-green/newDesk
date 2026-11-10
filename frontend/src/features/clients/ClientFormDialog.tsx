import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import type { Client, SaveClientInput } from './types'
import { useSaveClient } from './useClients'

interface ClientFormDialogProps {
  client: Client | null
  onClose: () => void
  onSaved: (client: Client) => void
}

type FormState = Record<keyof SaveClientInput, string>

const textFields: { name: Exclude<keyof SaveClientInput, 'notes'>; label: string; type?: string; hint?: string }[] = [
  { name: 'legal_name', label: 'Razón social' },
  { name: 'trade_name', label: 'Nombre comercial' },
  { name: 'tax_id', label: 'CUIT', hint: 'Por ejemplo 30-71234567-8' },
  { name: 'contact_name', label: 'Contacto' },
  { name: 'email', label: 'Email', type: 'email' },
  { name: 'phone', label: 'Teléfono', type: 'tel' },
]

function initialForm(client: Client | null): FormState {
  return {
    legal_name: client?.legal_name ?? '',
    trade_name: client?.trade_name ?? '',
    tax_id: client?.tax_id ?? '',
    contact_name: client?.contact_name ?? '',
    email: client?.email ?? '',
    phone: client?.phone ?? '',
    notes: client?.notes ?? '',
  }
}

function toInput(form: FormState): SaveClientInput {
  const orNull = (value: string) => (value.trim() === '' ? null : value.trim())
  return {
    legal_name: form.legal_name.trim(),
    trade_name: orNull(form.trade_name),
    tax_id: orNull(form.tax_id),
    contact_name: orNull(form.contact_name),
    email: orNull(form.email),
    phone: orNull(form.phone),
    notes: orNull(form.notes),
  }
}

/** RF-02.01 create / edit. */
export function ClientFormDialog({ client, onClose, onSaved }: ClientFormDialogProps) {
  const [form, setForm] = useState(() => initialForm(client))
  const mutation = useSaveClient(client?.id ?? null)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate(toInput(form), { onSuccess: onSaved })
  }

  return (
    <Dialog title={client ? `Editar ${client.display_name}` : 'Nuevo cliente'} size="lg" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          {textFields.map((field) => (
            <TextField
              key={field.name}
              label={field.label}
              type={field.type ?? 'text'}
              hint={field.hint}
              required={field.name === 'legal_name'}
              value={form[field.name]}
              error={fieldErrorOf(mutation.error, field.name)}
              onChange={(event) => setForm({ ...form, [field.name]: event.target.value })}
            />
          ))}
        </div>
        <TextAreaField
          label="Notas"
          value={form.notes}
          error={fieldErrorOf(mutation.error, 'notes')}
          onChange={(event) => setForm({ ...form, notes: event.target.value })}
        />
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" loading={mutation.isPending}>
            {client ? 'Guardar cambios' : 'Crear cliente'}
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
