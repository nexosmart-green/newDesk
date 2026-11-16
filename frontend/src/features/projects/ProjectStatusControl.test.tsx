import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { buildProject } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { ProjectStatusControl } from './ProjectStatusControl'
import * as projectsApi from './projectsApi'

const openMenu = () => userEvent.click(screen.getByRole('button', { name: 'Estado: Activo. Cambiar estado' }))

describe('ProjectStatusControl', () => {
  it('is the status badge, opening a menu with the current status checked', async () => {
    renderAt(<ProjectStatusControl project={buildProject()} />)

    await openMenu()

    const menu = screen.getByRole('menu')
    expect(within(menu).getByRole('menuitemradio', { name: 'Activo' })).toHaveAttribute('aria-checked', 'true')
    expect(within(menu).getByRole('menuitemradio', { name: 'En pausa' })).toHaveAttribute('aria-checked', 'false')
    expect(within(menu).getByRole('menuitemradio', { name: 'Activo' })).toHaveFocus()
    await userEvent.keyboard('{Escape}')
    expect(screen.queryByRole('menu')).not.toBeInTheDocument()
  })

  it('names the action and, when it stops timers, confirms it in danger style saying what happens', async () => {
    const project = buildProject()
    const change = vi.spyOn(projectsApi, 'changeProjectStatus').mockResolvedValue({ ...project, status: 'cancelled' })
    renderAt(<ProjectStatusControl project={project} />)

    await openMenu()
    await userEvent.click(screen.getByRole('menuitemradio', { name: 'Cancelado' }))

    const confirm = screen.getByRole('dialog', { name: `¿Cancelar «${project.name}»?` })
    expect(within(confirm).getByText(/cronómetros que estén corriendo en este proyecto se detienen ahora/)).toBeInTheDocument()
    expect(within(confirm).getByRole('button', { name: 'Volver' })).toHaveFocus()
    const confirmButton = within(confirm).getByRole('button', { name: 'Cancelar proyecto' })
    expect(confirmButton.className).toContain('bg-danger')
    await userEvent.click(confirmButton)

    expect(change).toHaveBeenCalledWith(project.id, 'cancelled')
    expect(await screen.findByText(`Proyecto «${project.name}» cancelado.`)).toBeInTheDocument()
  })

  it('pauses without a confirmation (timers keep running)', async () => {
    const project = buildProject()
    const change = vi.spyOn(projectsApi, 'changeProjectStatus').mockResolvedValue({ ...project, status: 'paused' })
    renderAt(<ProjectStatusControl project={project} />)

    await openMenu()
    await userEvent.click(screen.getByRole('menuitemradio', { name: 'En pausa' }))

    expect(change).toHaveBeenCalledWith(project.id, 'paused')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('is a plain badge for whoever cannot change it', () => {
    renderAt(<ProjectStatusControl project={buildProject({ can: { update: false, manage_members: false } })} />)

    expect(screen.getByText('Activo')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
