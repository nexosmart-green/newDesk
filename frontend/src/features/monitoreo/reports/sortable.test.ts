import { dropIndex, moveItem, settleOffset, shiftOf, type Slot } from './sortable'

// Tres tarjetas de 60 px con 6 px entre medio, y una cuarta más alta.
const slots: Slot[] = [
  { top: 0, height: 60 },
  { top: 66, height: 60 },
  { top: 132, height: 60 },
  { top: 198, height: 90 },
]

describe('arrastre del roadmap', () => {
  it('la primera llega al final aunque se suelte debajo de la última, en el vacío', () => {
    expect(dropIndex(slots, 0, 400)).toBe(3)
    expect(dropIndex(slots, 0, 230)).toBe(3) // pasó el centro de la última
    expect(dropIndex(slots, 0, 180)).toBe(2) // todavía no
  })

  it('cuenta los huecos entre tarjetas y no sale de la lista', () => {
    expect(dropIndex(slots, 0, 70)).toBe(1) // en el hueco entre la 2 y la 3: después de la 2
    expect(dropIndex(slots, 3, -500)).toBe(0)
    expect(dropIndex(slots, 1, 0)).toBe(1)
  })

  it('las demás se corren el alto de la arrastrada más el espacio', () => {
    // La primera baja al final: las otras suben 66.
    expect([1, 2, 3].map((index) => shiftOf(index, 0, 3, slots))).toEqual([-66, -66, -66])
    // La última (90 de alto) sube a la segunda posición: la 2 y la 3 bajan 96; la 1 no se mueve.
    expect([0, 1, 2].map((index) => shiftOf(index, 3, 1, slots))).toEqual([0, 96, 96])
  })

  it('al soltar se acomoda justo en el lugar que le hicieron', () => {
    expect(settleOffset(0, 3, slots)).toBe(228) // termina donde terminaba la última: 288 - 60
    expect(settleOffset(3, 1, slots)).toBe(-132)
    expect(settleOffset(2, 2, slots)).toBe(0)
  })

  it('mueve el ítem', () => {
    expect(moveItem([1, 2, 3, 4], 0, 3)).toEqual([2, 3, 4, 1])
    expect(moveItem([1, 2, 3, 4], 3, 1)).toEqual([1, 4, 2, 3])
  })
})
