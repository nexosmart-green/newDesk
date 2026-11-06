import { toCsv } from './csv'

describe('toCsv', () => {
  it('joins with ; and quotes cells that need it', () => {
    expect(toCsv([['Proyecto', 'Cliente'], ['Bahía; Web', null], ['Dice "hola"', 3]])).toBe(
      'Proyecto;Cliente\r\n"Bahía; Web";\r\n"Dice ""hola""";3',
    )
  })
})
