import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { buildProject } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { ProjectStatusControl } from './ProjectStatusControl'
import * as projectsApi from './projectsApi'

describe('ProjectStatusControl', () => {
  it('names the action and, when it stops timers, confirms it in danger style saying what happens', async () => {
    const project = buildProject()
    const change = vi.spyOn(projectsApi, 'changeProjectStatus').mockResolvedValue({ ...project, status: 'cancelled' })
    renderAt(<ProjectStatusControl project={project} />)

    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'Cancelado')
    await userEvent.click(screen.getByRole('button', { name: 'Cancelar proyecto' }))

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

    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'En pausa')
    await userEvent.click(screen.getByRole('button', { name: 'Pausar proyecto' }))

    expect(change).toHaveBeenCalledWith(project.id, 'paused')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
