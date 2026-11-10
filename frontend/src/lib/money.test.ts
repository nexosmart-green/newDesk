import { formatMoney, parseMoneyToCents } from './money'

describe('money', () => {
  it('parses Argentine and plain decimals to cents', () => {
    expect(parseMoneyToCents('1500')).toBe(150000)
    expect(parseMoneyToCents('1.500,50')).toBe(150050)
    expect(parseMoneyToCents('1500.5')).toBe(150050)
    expect(parseMoneyToCents('abc')).toBeNull()
    expect(parseMoneyToCents('-3')).toBeNull()
  })

  it('formats cents in the given currency', () => {
    expect(formatMoney(150050, 'ARS')).toMatch(/1\.500,50/)
  })
})
