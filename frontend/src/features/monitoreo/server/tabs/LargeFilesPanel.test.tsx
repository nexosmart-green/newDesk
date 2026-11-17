import { screen, within } from '@testing-library/react'
import { largeFiles, projects } from '../../test/fixtures/clientes'
import { renderWithProviders } from '../../test/render'
import { LargeFilesPanel } from './LargeFilesPanel'
import { ProjectsTab } from './ProjectsTab'

describe('LargeFilesPanel', () => {
  it('lista el archivo más pesado primero, con la ruta absoluta para copiar', () => {
    renderWithProviders(<LargeFilesPanel data={largeFiles} projects={projects.items} />)
    expect(screen.getByText('Archivos pesados · 23')).toBeInTheDocument()
    const first = screen.getAllByRole('row')[1] as HTMLElement
    expect(first).toHaveTextContent('medplace-backend')
    expect(first).toHaveTextContent('storage/logs/laravel.log')
    expect(within(first).getByRole('button', { name: /storage\/logs\/laravel\.log/ })).toHaveAttribute('title', 'Copiar ruta: /var/www/clientes.nexosmart.com.ar/medplace-backend/storage/logs/laravel.log')
  })

  it('avisa lo que no se lista y las búsquedas incompletas', () => {
    renderWithProviders(<LargeFilesPanel data={largeFiles} projects={projects.items} />)
    expect(screen.getByText(/2 archivos pesados más sin listar/)).toBeInTheDocument()
    expect(screen.getByText(/3 carpetas sin permiso de lectura/)).toBeInTheDocument()
  })

  // NV-07: los nombres los elige cualquier usuario del server y la ruta se pega en una shell.
  it('solo ofrece copiar rutas sin caracteres de shell', () => {
    const file = (path: string, size: number) => ({ path, size_bytes: size, modified_at: '2026-09-30T12:00:00Z' })
    const data = {
      threshold_bytes: 50 * 1024 ** 2, truncated: false,
      projects: [{
        project: '/var/www/site', count: 3, total_bytes: 600, scanned: 10, unreadable: 0, truncated: false,
        files: [file('storage/dump-2026_09@v1+x.sql', 300), file('a;touch PWNED;#.log', 200), file('logs/a\nrm -rf ~.log', 100)],
      }],
    }
    renderWithProviders(<LargeFilesPanel data={data} />)
    const [, plain, injected, newline] = screen.getAllByRole('row') as [HTMLElement, HTMLElement, HTMLElement, HTMLElement]
    expect(within(plain).getByRole('button', { name: /dump-2026_09@v1\+x\.sql/ })).toHaveAttribute('title', 'Copiar ruta: /var/www/site/storage/dump-2026_09@v1+x.sql')
    expect(injected).toHaveTextContent('a;touch PWNED;#.log')
    expect(within(injected).queryByRole('button')).not.toBeInTheDocument()
    expect(newline).toHaveTextContent('rm -rf ~.log')
    expect(within(newline).queryByRole('button')).not.toBeInTheDocument()
  })

  it('sin archivos pesados lo dice con el umbral', () => {
    renderWithProviders(<LargeFilesPanel data={{ threshold_bytes: 50 * 1024 ** 2, truncated: false, projects: [] }} />)
    expect(screen.getByText('Ningún archivo de 50 MB o más en los proyectos.')).toBeInTheDocument()
  })
})

describe('ProjectsTab con archivos pesados', () => {
  it('marca el proyecto con la cantidad y el total', () => {
    renderWithProviders(<ProjectsTab data={projects} largeFiles={largeFiles} />)
    expect(screen.getByText(/1 archivo pesado ·/)).toBeInTheDocument()
  })
})
