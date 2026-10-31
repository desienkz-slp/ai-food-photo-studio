import { render, screen } from '@testing-library/react'
import { vi } from 'vitest'
import Page from './page'

describe('Halaman utama', () => {
  it('memerlukan login: menampilkan layar masuk untuk tamu tanpa mengirim kredensial', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockImplementation(async (url) => url === '/csrf-cookie' ? new Response(null, { status: 204 }) : new Response(null, { status: 401 }))
    try {
      render(<Page />)
      expect(await screen.findByText('Selamat Datang')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Masuk' })).toBeDisabled()
      expect(screen.queryByRole('heading', { name: 'Pengaturan provider' })).not.toBeInTheDocument()
      expect(fetchSpy.mock.calls.every(([url]) => url === '/csrf-cookie' || url === '/session')).toBe(true)
    } finally {
      fetchSpy.mockRestore()
    }
  })
})
