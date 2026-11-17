import type { Project } from '../api/contract'
import { inventoryProjectFor, lastSegment } from './projects'

const project = (path: string): Project => ({
  path, name: lastSegment(path), type: 'laravel', markers: [], owner: 'root:root', mode: '755', outside_web_root: false,
})

describe('inventoryProjectFor', () => {
  const projects = [project('/var/www/site/playinn'), project('/var/www/site/play'), project('/var/www/site/playinn/back')]

  it('elige el ancestro más cercano del inventario', () => {
    expect(inventoryProjectFor('/var/www/site/playinn/back', projects)?.path).toBe('/var/www/site/playinn/back')
    expect(inventoryProjectFor('/var/www/site/playinn/front/apps/web', projects)?.path).toBe('/var/www/site/playinn')
  })

  it('no confunde prefijos de nombre con carpetas', () => {
    expect(inventoryProjectFor('/var/www/site/playground', projects)).toBeUndefined()
  })
})
