import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import type { NewPasswordInput } from './types'

const PASSWORD_RULE_HINT = 'Mínimo 10 caracteres, con letras y números.'

interface NewPasswordFieldsProps {
  value: NewPasswordInput
  onChange: (value: NewPasswordInput) => void
  error: unknown
}

export function NewPasswordFields({ value, onChange, error }: NewPasswordFieldsProps) {
  return (
    <>
      <TextField
        label="Nueva contraseña"
        type="password"
        required
        minLength={10}
        autoComplete="new-password"
        hint={PASSWORD_RULE_HINT}
        value={value.password}
        error={fieldErrorOf(error, 'password')}
        onChange={(event) => onChange({ ...value, password: event.target.value })}
      />
      <TextField
        label="Repetí la contraseña"
        type="password"
        required
        autoComplete="new-password"
        value={value.password_confirmation}
        error={fieldErrorOf(error, 'password_confirmation')}
        onChange={(event) => onChange({ ...value, password_confirmation: event.target.value })}
      />
    </>
  )
}

