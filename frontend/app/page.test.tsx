import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi } from 'vitest'
import Page from './page'

describe('Halaman utama', () => {
  it('menampilkan pengaturan provider tanpa mengaktifkan penyimpanan atau koneksi', async () => {
    const user = userEvent.setup()
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockImplementation(async (url) => url === '/csrf-cookie' ? new Response(null, { status: 204 }) : new Response(null, { status: 401 }))
    try {
      render(<Page />)
      expect(screen.getByRole('heading', { name: 'Pengaturan provider' })).toBeInTheDocument()
      expect(screen.getAllByText('Belum terhubung').length).toBeGreaterThan(0)
      await user.type(screen.getByLabelText('API key'), 'secret-test-key')
      expect(screen.getByRole('button', { name: 'Muat model' })).toBeDisabled()
      expect(screen.getByRole('button', { name: 'Simpan pengaturan' })).toBeDisabled()
      await user.click(screen.getByRole('button', { name: 'Simpan pengaturan' }))
      expect(fetchSpy.mock.calls.every(([url]) => url === '/csrf-cookie' || url === '/session')).toBe(true)
      expect(screen.queryByText(/berhasil|sukses/i)).not.toBeInTheDocument()
    } finally {
      fetchSpy.mockRestore()
    }
  })
})
