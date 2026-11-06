import { centeredSquareCrop } from './squareCrop'

describe('centeredSquareCrop', () => {
  it('takes the largest centered square of a landscape photo', () => {
    expect(centeredSquareCrop(1200, 800, 1)).toEqual({ sx: 200, sy: 0, side: 800 })
  })

  it('zooms in around the center and clamps the zoom', () => {
    expect(centeredSquareCrop(800, 1200, 2)).toEqual({ sx: 200, sy: 400, side: 400 })
    expect(centeredSquareCrop(800, 800, 10)).toEqual(centeredSquareCrop(800, 800, 3))
  })
})
