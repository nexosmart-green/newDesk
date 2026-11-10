import { PeriodNav } from '@/components/ui/PeriodNav'
import { shiftWeek, weekLabel, type Week } from './week'

interface WeekNavProps {
  week: Week
  isCurrentWeek: boolean
  onChange: (monday: string | null) => void
}

export function WeekNav({ week, isCurrentWeek, onChange }: WeekNavProps) {
  return (
    <PeriodNav
      label="Elegir semana"
      current={weekLabel(week)}
      previousLabel="Semana anterior"
      todayLabel="Esta semana"
      nextLabel="Semana siguiente"
      isToday={isCurrentWeek}
      onPrevious={() => onChange(shiftWeek(week.from, -1).from)}
      onToday={() => onChange(null)}
      onNext={() => onChange(shiftWeek(week.from, 1).from)}
    />
  )
}
