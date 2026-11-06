import { useState } from 'react'
import { Tabs, type TabItem } from '@/components/ui/Tabs'
import { useCan } from '@/features/auth/useAuth'
import { ShiftTemplatesPanel } from './ShiftTemplatesPanel'
import { ShiftWeekBoard } from './ShiftWeekBoard'
import { SHIFTS_PERMISSION } from './types'

type ShiftsTab = 'grilla' | 'plantillas'

const tabs: readonly TabItem<ShiftsTab>[] = [
  { id: 'grilla', label: 'Semana' },
  { id: 'plantillas', label: 'Plantillas' },
]

/** RF-06.01/02 "Turnos": weekly grid people × days and the templates it assigns. `shifts.manage`. */
export function ShiftsPage() {
  const canManage = useCan(SHIFTS_PERMISSION)
  const [tab, setTab] = useState<ShiftsTab>('grilla')

  if (!canManage) return <p className="text-muted">No tenés acceso a los turnos del equipo.</p>

  return (
    <section className="mx-auto max-w-6xl space-y-4">
      <h1 className="text-lg font-semibold">Turnos</h1>
      <Tabs label="Secciones de turnos" tabs={tabs} selected={tab} onSelect={setTab}>
        {tab === 'grilla' ? <ShiftWeekBoard /> : <ShiftTemplatesPanel />}
      </Tabs>
    </section>
  )
}
