import {
  addPart,
  clockLabel,
  colorsByProject,
  fillGap,
  gapsOf,
  handlesOf,
  hourTicks,
  initialParts,
  isCompletePart,
  moveHandle,
  offsetOfTime,
  setPartEdge,
  snapOffset,
  stepOffset,
  toPartInput,
  type DayPart,
} from './dayParts'

const part = (key: number, from: number, to: number, overrides: Partial<DayPart> = {}): DayPart => ({
  key,
  from,
  to,
  projectId: null,
  taskId: null,
  newTaskTitle: null,
  comment: '',
  ...overrides,
})
const spans = (parts: DayPart[]) => parts.map(({ from, to }) => [from, to])

describe('day split math (RF-05.19)', () => {
  it('starts with one part covering the whole day, or none for an empty clock', () => {
    expect(spans(initialParts(3600))).toEqual([[0, 3600]])
    expect(initialParts(0)).toEqual([])
  })

  it('finds the gaps (not counted) and fills one with a new part', () => {
    const parts = [part(1, 600, 1200), part(2, 1800, 2400)]
    expect(gapsOf(parts, 3000)).toEqual([
      { from: 0, to: 600 },
      { from: 1200, to: 1800 },
      { from: 2400, to: 3000 },
    ])
    const filled = fillGap(parts, { from: 1200, to: 1800 })
    expect(spans(filled)).toEqual([[600, 1200], [1200, 1800], [1800, 2400]])
    expect(filled[1]?.key).toBe(3)
  })

  it('snaps drags to whole wall-clock minutes and steps arrow keys minute by minute', () => {
    // Started at hh:mm:37 → minute boundaries sit at offsets 23, 83, 143…
    expect(snapOffset(100, 37)).toBe(83)
    expect(stepOffset(0, 1, 37)).toBe(23)
    expect(stepOffset(23, 1, 37)).toBe(83)
    expect(stepOffset(83, -1, 37)).toBe(23)
    expect(stepOffset(0, 15, 37)).toBe(15 * 60 - 37)
  })

  it('a cut between two parts moves both edges and never leaves a part under a minute', () => {
    const parts = [part(1, 0, 1000), part(2, 1000, 2000)]
    const handles = handlesOf(parts)
    expect(handles.map((handle) => `${handle.kind}@${handle.value}`)).toEqual(['from@0', 'cut@1000', 'to@2000'])

    const cut = handles[1]!
    expect(spans(moveHandle(parts, cut, 1500, 2000))).toEqual([[0, 1500], [1500, 2000]])
    expect(spans(moveHandle(parts, cut, 5000, 2000))).toEqual([[0, 1940], [1940, 2000]])
    // A lone edge stops at the neighbour.
    const gapped = [part(1, 0, 500), part(2, 1000, 2000)]
    const end = handlesOf(gapped).find((handle) => handle.kind === 'to' && handle.index === 0)!
    expect(spans(moveHandle(gapped, end, 1800, 2000))).toEqual([[0, 1000], [1000, 2000]])
  })

  it('a typed time pushes into the neighbour or opens a gap, never overlapping', () => {
    const parts = [part(1, 0, 1000), part(2, 1000, 2000)]
    expect(spans(setPartEdge(parts, 0, 'to', 1300, 2000))).toEqual([[0, 1300], [1300, 2000]])
    expect(spans(setPartEdge(parts, 0, 'to', 700, 2000))).toEqual([[0, 700], [1000, 2000]])
    expect(spans(setPartEdge(parts, 1, 'from', -50, 2000))).toEqual([[0, 60], [60, 2000]])
    expect(spans(setPartEdge(parts, 1, 'to', 9999, 2000))).toEqual([[0, 1000], [1000, 2000]])
  })

  it('"Agregar parte" fills the biggest gap, else halves the longest part', () => {
    expect(spans(addPart([part(1, 0, 600), part(2, 900, 1000)], 3000, 0))).toEqual([[0, 600], [900, 1000], [1000, 3000]])
    expect(spans(addPart(initialParts(7200), 7200, 0))).toEqual([[0, 3600], [3600, 7200]])
  })

  it('converts typed HH:mm to offsets from the zoned start, after midnight included', () => {
    expect(offsetOfTime('11:37', '09:02', 37, 33_083)).toBe(155 * 60 - 37)
    expect(offsetOfTime('00:00', '23:30', 0, 1800)).toBe(1800)
    expect(offsetOfTime('08:00', '09:02', 37, 33_083)).toBeLessThan(0)
  })

  it('labels offsets in the profile timezone and ticks every whole hour', () => {
    const startedAt = '2026-11-10T12:02:37+00:00'
    expect(clockLabel(startedAt, 0, 'America/Argentina/Buenos_Aires')).toBe('09:02')
    expect(clockLabel(startedAt, 155 * 60 - 37, 'America/Argentina/Buenos_Aires')).toBe('11:37')
    const ticks = hourTicks(33_083, '09:02', 37)
    expect(ticks.map((tick) => clockLabel(startedAt, tick, 'America/Argentina/Buenos_Aires'))).toEqual([
      '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00',
    ])
  })

  it('a part is complete with project, task (or new title) and a real comment; builds the payload', () => {
    const newTask = part(1, 0, 600, { projectId: 9, newTaskTitle: '  Reunión semanal ', comment: 'Reunión con el cliente' })
    expect(isCompletePart(newTask)).toBe(true)
    expect(isCompletePart({ ...newTask, newTaskTitle: '  ' })).toBe(false)
    expect(isCompletePart({ ...newTask, comment: 'corto' })).toBe(false)
    expect(toPartInput(newTask)).toEqual({ from: 0, to: 600, project_id: 9, task_id: null, new_task_title: 'Reunión semanal', comment: 'Reunión con el cliente' })
    expect(toPartInput({ ...newTask, newTaskTitle: null, taskId: 60 })).toMatchObject({ task_id: 60, new_task_title: null })
  })

  it('gives each project of the day its own color by first appearance', () => {
    const colors = colorsByProject([part(1, 0, 1, { projectId: 7 }), part(2, 1, 2, { projectId: 3 }), part(3, 2, 3, { projectId: 7 })])
    expect(colors.size).toBe(2)
    expect(colors.get(7)).not.toEqual(colors.get(3))
  })
})
