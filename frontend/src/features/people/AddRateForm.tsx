import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { useCurrentUser } from '@/features/auth/useAuth'
import { fieldErrorOf } from '@/lib/api'
import { parseMoneyToCents } from '@/lib/money'
import { useAddRate } from './usePeople'

export function AddRateForm({ personId }: { personId: number }) {
  const { data: user } = useCurrentUser()
  const [amount, setAmount] = useState('')
  const [validFrom, setValidFrom] = useState('')
  const [amountError, setAmountError] = useState<string | null>(null)
  const mutation = useAddRate(personId)
  const currency = user?.company.currency ?? 'ARS'

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const cents = parseMoneyToCents(amount)
    if (cents === null) return setAmountError('Ingresá un monto válido, por ejemplo 1500,50.')
    setAmountError(null)
    mutation.mutate(
      { amount_cents: cents, valid_from: validFrom },
      {
        onSuccess: () => {
          setAmount('')
          setValidFrom('')
        },
      },
    )
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3 rounded-lg border border-border p-4" noValidate>
      <h2 className="font-semibold">Nueva tarifa</h2>
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField
          label={`Monto por hora (${currency})`}
          inputMode="decimal"
          required
          value={amount}
          error={amountError ?? fieldErrorOf(mutation.error, 'amount_cents')}
          onChange={(event) => setAmount(event.target.value)}
        />
        <TextField
          label="Vigente desde"
          type="date"
          required
          value={validFrom}
          error={fieldErrorOf(mutation.error, 'valid_from')}
          onChange={(event) => setValidFrom(event.target.value)}
        />
      </div>
      <p className="text-xs text-muted">La tarifa anterior queda vigente hasta el día previo.</p>
      <SuccessNotice message={mutation.isSuccess ? 'Tarifa registrada.' : null} />
      <ErrorAlert error={mutation.error} />
      <Button type="submit" disabled={mutation.isPending || !amount || !validFrom}>
        {mutation.isPending ? 'Guardando…' : 'Registrar tarifa'}
      </Button>
    </form>
  )
}
