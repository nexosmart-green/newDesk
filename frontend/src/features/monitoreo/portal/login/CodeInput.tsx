import { useEffect, useRef } from 'react'

interface CodeInputProps {
  value: string
  onChange: (value: string) => void
  length?: number
  label: string
  invalid?: boolean
  autoFocus?: boolean
  disabled?: boolean
}

// Campo único de código numérico (acepta pegar "123 456"). Un solo input
// es más accesible y funciona con el autocompletado de OTP del sistema.
export function CodeInput({ value, onChange, length = 6, label, invalid, autoFocus, disabled }: CodeInputProps) {
  const ref = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (autoFocus) ref.current?.focus()
  }, [autoFocus])

  return (
    <label className="block">
      <span className="label mb-1.5 block">{label}</span>
      <input
        ref={ref}
        className="input num h-14 text-center text-[1.75rem] font-semibold tracking-[0.5em]"
        inputMode="numeric"
        autoComplete="one-time-code"
        pattern="[0-9]*"
        maxLength={length + 2}
        value={value}
        disabled={disabled}
        aria-invalid={invalid ? 'true' : 'false'}
        placeholder={'·'.repeat(length)}
        onChange={(event) => onChange(event.target.value.replace(/\D/g, '').slice(0, length))}
      />
    </label>
  )
}
