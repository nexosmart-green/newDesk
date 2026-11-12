import type { ChecklistItem, TaskAttachment } from '@/features/tasks/types'

export function buildChecklistItem(overrides: Partial<ChecklistItem> = {}): ChecklistItem {
  return {
    id: 1,
    text: 'Revisar el login',
    position: 1,
    done: false,
    done_at: null,
    done_by: null,
    ...overrides,
  }
}

export function buildAttachment(overrides: Partial<TaskAttachment> = {}): TaskAttachment {
  const uuid = overrides.uuid ?? 'a1'
  return {
    id: 1,
    uuid,
    name: 'captura.png',
    mime: 'image/png',
    size: 245_760,
    url: `/api/v1/task-attachments/${uuid}`,
    is_image: true,
    uploaded_by: { id: 1, full_name: 'Ana García' },
    created_at: '2026-11-10T13:00:00+00:00',
    can_delete: true,
    ...overrides,
  }
}
