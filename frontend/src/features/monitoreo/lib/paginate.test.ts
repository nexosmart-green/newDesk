import { expect, it } from 'vitest'
import { pagesOf, paginate } from './paginate'

const list = (n: number) => Array.from({ length: n }, (_, index) => index + 1)

it('con menos de 20 filas muestra todo en una página', () => {
  expect(paginate(list(0), 1)).toMatchObject({ rows: [], page: 1, pages: 1 })
  const nineteen = paginate(list(19), 2)
  expect(nineteen).toMatchObject({ page: 1, pages: 1, per_page: 20, total: 19 })
  expect(nineteen.rows).toHaveLength(19)
})

it('con 20 o más pagina de a 10 y ajusta la página pedida', () => {
  expect(paginate(list(20), 2).rows).toEqual(list(20).slice(10))
  expect(paginate(list(23), 3)).toMatchObject({ rows: [21, 22, 23], page: 3, pages: 3, per_page: 10 })
  expect(paginate(list(23), 9).page).toBe(3)
  expect(paginate(list(23), 0).page).toBe(1)
  expect(paginate(list(23), Number.NaN).page).toBe(1)
})

it('con perPage fijo pagina de a ese tamaño aunque sean pocas filas', () => {
  expect(paginate(list(5), 1, 5)).toMatchObject({ rows: list(5), pages: 1 })
  expect(paginate(list(12), 3, 5)).toMatchObject({ rows: [11, 12], page: 3, pages: 3, per_page: 5 })
  expect(paginate(list(12), 9, 5).page).toBe(3)
})

it('calcula las páginas desde el meta del backend', () => {
  expect(pagesOf({ total: 0, per_page: 20 })).toBe(1)
  expect(pagesOf({ total: 23, per_page: 10 })).toBe(3)
})
