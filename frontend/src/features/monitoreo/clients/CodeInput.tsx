// El código del proyecto de newDesk (RF-03.01), que monitoreo no tenía: minúsculas y dígitos, de 2 a 12, único.
// El back valida; acá solo se descarta lo que nunca sería válido, como los números en los campos de ComponentForm.
export const CODE_PATTERN = /^[a-z0-9]{2,12}$/

export function CodeInput({ value, onChange, invalid }: { value: string; onChange: (code: string) => void; invalid?: boolean }) {
  return (
    <input
      className="input font-mono text-xs"
      value={value}
      maxLength={12}
      autoComplete="off"
      spellCheck={false}
      placeholder="playinn"
      aria-invalid={invalid || undefined}
      onChange={(event) => onChange(event.target.value.toLowerCase().replace(/[^a-z0-9]/g, ''))}
    />
  )
}
