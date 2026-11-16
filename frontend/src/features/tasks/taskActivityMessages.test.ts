import type { ActivityEntry } from '@/features/projects/types'
import { describeTaskActivity } from './taskActivityMessages'

function entry(event: string, before: Record<string, unknown> | null, after: Record<string, unknown> | null): ActivityEntry {
  return { id: 1, event, actor: { id: 1, full_name: 'Ana García' }, source: 'web', token_name: null, before, after, created_at: '2026-11-12T12:00:00+00:00' }
}

describe('describeTaskActivity: checklist and files', () => {
  it('names the step or the file in each sentence', () => {
    expect(describeTaskActivity(entry('checklist.added', null, { text: 'Probar en Safari' }))).toBe('Ana García agregó la subtarea «Probar en Safari»')
    expect(describeTaskActivity(entry('checklist.checked', null, { text: 'Probar en Safari' }))).toBe('Ana García tildó la subtarea «Probar en Safari»')
    expect(describeTaskActivity(entry('checklist.unchecked', null, { text: 'Probar' }))).toBe('Ana García destildó la subtarea «Probar»')
    expect(describeTaskActivity(entry('checklist.renamed', { text: 'Probar' }, { text: 'Probar en Safari' }))).toBe(
      'Ana García renombró la subtarea «Probar» a «Probar en Safari»',
    )
    expect(describeTaskActivity(entry('checklist.removed', { text: 'Probar' }, null))).toBe('Ana García borró la subtarea «Probar»')
    expect(describeTaskActivity(entry('attachment.added', null, { name: 'presupuesto.pdf' }))).toBe('Ana García adjuntó «presupuesto.pdf»')
    expect(describeTaskActivity(entry('attachment.removed', { name: 'presupuesto.pdf' }, null))).toBe('Ana García borró el archivo «presupuesto.pdf»')
  })
})
