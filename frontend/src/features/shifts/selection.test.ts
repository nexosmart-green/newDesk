import { selectionToAssignments } from './selection'

const week = { from: '2026-11-02', to: '2026-11-08' }

describe('selectionToAssignments', () => {
  it('groups people that share the same weekdays into one request', () => {
    const cells = [
      { userId: 1, date: '2026-11-02' },
      { userId: 2, date: '2026-11-02' },
      { userId: 3, date: '2026-11-08' },
    ]

    expect(selectionToAssignments(cells, week, null)).toEqual([
      { user_ids: [1, 2], shift_template_id: null, ...week, weekdays: [1] },
      { user_ids: [3], shift_template_id: null, ...week, weekdays: [7] },
    ])
  })
})
