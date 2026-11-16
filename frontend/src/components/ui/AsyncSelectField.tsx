import type { ComponentProps } from 'react'
import { SelectField } from './SelectField'

interface AsyncSelectFieldProps extends ComponentProps<typeof SelectField> {
  /** The options are still on their way: the empty option says so and the field is marked busy. */
  loading: boolean
  /** Text of the empty option (`value=""`) once the options are in: "Sin asignar", "Sin cliente"… */
  emptyLabel: string
}

/** A select whose options come from the server. Only the field waits: the rest of the form stays usable. */
export function AsyncSelectField({ loading, emptyLabel, children, ...selectProps }: AsyncSelectFieldProps) {
  return (
    <SelectField aria-busy={loading || undefined} {...selectProps}>
      <option value="">{loading ? 'Cargando…' : emptyLabel}</option>
      {children}
    </SelectField>
  )
}
