/**
 * Every React Query key of the app, in one place. Keys are hierarchical: invalidating a root (e.g.
 * `tasksKey`) refreshes everything under it, so a mutation picks the narrowest key that covers what it
 * changed. Features import keys from here, never from each other's hooks (no import cycles).
 */

const lower = (code: string) => code.toLowerCase()

// Session
/** `/me`. Logout keeps only this query, so nothing else may live under it. */
export const currentUserKey = ['me'] as const
export const invitationKey = (token: string) => ['invitations', token] as const

// Tasks
export const tasksKey = ['tasks'] as const
export const projectTasksKey = (projectId: number) => [...tasksKey, 'project', projectId] as const
export const myTasksKey = [...tasksKey, 'mine'] as const
export const allTasksKey = [...tasksKey, 'all'] as const
/** Every task list and board (not the details): what a change to one task's row badges touches. */
export const taskListsKeys = [[...tasksKey, 'project'], myTasksKey, allTasksKey] as const
/** The detail; its tabs (comments, attachments, hours, history) live under it. */
export const taskKey = (code: string) => [...tasksKey, 'detail', lower(code)] as const
export const taskCommentsKey = (code: string) => [...taskKey(code), 'comments'] as const
export const taskAttachmentsKey = (code: string) => [...taskKey(code), 'attachments'] as const
export const taskTimeEntriesKey = (code: string) => [...taskKey(code), 'time-entries'] as const
export const taskActivityKey = (code: string) => [...taskKey(code), 'activity'] as const
export const taskStatusesKey = ['task-statuses'] as const
export const taskCategoriesKey = ['task-categories'] as const
export const taskLabelsKey = ['task-labels'] as const
/** Mutation key: checklist changes of one task refresh the lists once the last one settles. */
export const checklistMutationKey = (code: string) => ['checklist', lower(code)] as const
/** Mutation key: status changes in flight (each board card waits on its own). */
export const taskStatusMutationKey = [...tasksKey, 'status'] as const

/** The Panel: counters, "próximas a vencer", the week and the bags read tasks and time. */
export const dashboardKey = ['dashboard'] as const

// Time
export const timerKey = ['timer'] as const
export const hoursKey = ['hours'] as const
/** A project's bag and entries. */
export const projectHoursKey = (projectId: number) => [...hoursKey, projectId] as const
export const extensionRequestsKey = [...hoursKey, 'extension-requests'] as const
/** Mi semana and its history. */
export const timesheetKey = ['timesheet'] as const
export const timesheetWeekKey = (from: string, to: string) => [...timesheetKey, 'week', from, to] as const
export const timeHistoryKey = [...timesheetKey, 'history'] as const

// Projects and clients
export const projectsKey = ['projects'] as const
export const projectListsKey = [...projectsKey, 'list'] as const
export const projectKey = (id: number) => [...projectsKey, 'detail', id] as const
export const projectActivityKey = (id: number) => [...projectsKey, 'activity', id] as const
const clientsKey = ['clients'] as const
export const clientListsKey = [...clientsKey, 'list'] as const
export const clientKey = (id: number) => [...clientsKey, 'detail', id] as const

// People and organization
export const peopleKey = ['people'] as const
export const peopleListsKey = [...peopleKey, 'list'] as const
export const personKey = (id: number) => [...peopleKey, 'detail', id] as const
export const personRatesKey = (id: number) => [...peopleKey, 'rates', id] as const
/** Not under `me`: logout keeps only `me`, and the profile must not survive it. */
export const profileKey = ['profile'] as const
export const myTeamKey = [...profileKey, 'team'] as const
export const usersKey = ['users'] as const
export const rolesKey = ['roles'] as const
export const permissionsKey = ['permissions'] as const
export const companyKey = ['company'] as const
export const catalogKey = (type: string) => ['catalogs', type] as const
export const aiTokensKey = ['ai-tokens'] as const

// Notifications
export const notificationsKey = ['notifications'] as const
export const notificationPreferencesKey = ['notification-preferences'] as const
/** Mutation key: e-mail switches being saved (each box waits on its own). */
export const preferenceToggleMutationKey = [...notificationPreferencesKey, 'toggle'] as const

// Calendar and shifts
export const calendarKey = ['calendar'] as const
export const myCalendarKey = (from: string, to: string) => [...calendarKey, 'mine', from, to] as const
export const holidaysKey = (year: number) => [...calendarKey, 'holidays', year] as const
export const teamCalendarKey = (from: string, to: string) => [...calendarKey, 'team', from, to] as const
export const calendarFeedKey = ['calendar-feed'] as const
export const shiftsKey = ['shifts'] as const
export const shiftTemplatesKey = [...shiftsKey, 'templates'] as const
export const shiftGridKey = (from: string, to: string) => [...shiftsKey, 'grid', from, to] as const
export const shiftRecurrencesKey = [...shiftsKey, 'recurrences'] as const

// Reports
export const hoursReportsKey = ['reports', 'hours'] as const
