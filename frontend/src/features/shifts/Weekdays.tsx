import { useId } from 'react'
import { Button } from '@/components/ui/Button'
import { WHOLE_WEEK, WORKING_WEEK, weekdayInitials, weekdaysSummary } from './recurrenceTypes'

/** "L M M J V S D" with the rule's days filled in. Read as one phrase ("Lun a vie") by screen readers. */
export function WeekdayChips({ weekdays }: { weekdays: readonly number[] }) {
  return (
    <span role="img" aria-label={weekdaysSummary(weekdays)} className="inline-flex gap-1">
      {weekdayInitials.map((day) => {
        const on = weekdays.includes(day.value)
        return (
          <span
            key={day.value}
            aria-hidden="true"
            className={`grid size-6 place-items-center rounded-full text-[0.7rem] font-semibold ${
              on ? 'bg-brand-strong text-white' : 'border border-border text-muted'
            }`}
          >
            {day.initial}
          </span>
        )
      })}
    </span>
  )
}

interface WeekdayPickerProps {
  value: readonly number[]
  onChange: (weekdays: number[]) => void
  error?: string
}

/** Toggle buttons for each weekday plus the usual shortcuts. */
export function WeekdayPicker({ value, onChange, error }: WeekdayPickerProps) {
  const errorId = useId()
  const toggle = (day: number) => onChange(value.includes(day) ? value.filter((candidate) => candidate !== day) : [...value, day].sort((a, b) => a - b))

  return (
    <fieldset className="space-y-2" aria-describedby={error ? errorId : undefined}>
      <legend className="text-sm text-muted">Días de la semana</legend>
      <div className="flex flex-wrap items-center gap-1.5">
        {weekdayInitials.map((day) => {
          const on = value.includes(day.value)
          return (
            <button
              key={day.value}
              type="button"
              aria-pressed={on}
              aria-label={day.name}
              onClick={() => toggle(day.value)}
              className={`grid size-11 cursor-pointer place-items-center rounded-full text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent ${
                on ? 'bg-brand-strong text-white hover:bg-brand' : 'border border-border bg-bg text-muted hover:border-muted/60 hover:text-text'
              }`}
            >
              {day.initial}
            </button>
          )
        })}
      </div>
      <div className="flex flex-wrap gap-3 text-sm">
        <Button variant="link" onClick={() => onChange([...WORKING_WEEK])}>
          Lun a vie
        </Button>
        <Button variant="link" onClick={() => onChange([...WHOLE_WEEK])}>
          Todos los días
        </Button>
      </div>
      {error && (
        <p id={errorId} className="text-sm text-danger">
          {error}
        </p>
      )}
    </fieldset>
  )
}
