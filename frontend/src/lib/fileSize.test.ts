import { fileExtension, formatFileSize } from './fileSize'

describe('formatFileSize', () => {
  it('reads like the OS, with a decimal comma for small values', () => {
    expect(formatFileSize(820)).toBe('820 B')
    expect(formatFileSize(14 * 1024)).toBe('14 KB')
    expect(formatFileSize(3_565_158)).toBe('3,4 MB')
    expect(formatFileSize(20 * 1024 * 1024)).toBe('20 MB')
  })

  it('takes the last extension, lower-cased', () => {
    expect(fileExtension('informe.final.PDF')).toBe('pdf')
    expect(fileExtension('LEEME')).toBe('')
  })
})
