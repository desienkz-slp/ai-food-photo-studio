import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Workspace } from './workspace'

describe('Workspace', () => {
  it('menampilkan status belum terhubung dalam bahasa Indonesia', () => {
    render(<Workspace />)
    expect(screen.getAllByText('Belum terhubung').length).toBeGreaterThan(0)
    expect(screen.getByText(/server dan layanan AI belum terhubung/i)).toBeInTheDocument()
  })

  it('menonaktifkan unggah dan tidak menampilkan keberhasilan palsu', async () => {
    const user = userEvent.setup()
    render(<Workspace />)
    const upload = screen.getByRole('button', { name: /unggah/i })
    expect(upload).toBeDisabled()
    await user.click(upload)
    expect(screen.queryByText(/berhasil|sukses|selesai/i)).not.toBeInTheDocument()
    expect(screen.getByText(/unggah belum tersedia/i)).toBeInTheDocument()
  })

  it('menjelaskan bahwa hasil AI belum tersedia tanpa hasil buatan', () => {
    render(<Workspace />)
    expect(screen.getByText('Analisis AI belum tersedia')).toBeInTheDocument()
    expect(screen.getByText(/belum ada hasil untuk ditampilkan/i)).toBeInTheDocument()
  })
})
