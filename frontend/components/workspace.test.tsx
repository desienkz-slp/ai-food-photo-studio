import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi } from 'vitest'
import { Workspace } from './workspace'

// The workspace is gated behind login; simulate an authenticated session so
// its content renders. /workspaces returns empty so no network coupling.
const json = (data: unknown, status = 200) => new Response(JSON.stringify(data), { status })
beforeEach(() => {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (url) => {
    if (url === '/csrf-cookie') return new Response(null, { status: 204 })
    if (url === '/session') return json({ user: { id: 1, email: 'a@b.test' } })
    if (url === '/workspaces') return json({ data: [] })
    return new Response(null, { status: 404 })
  })
})
afterEach(() => vi.restoreAllMocks())

describe('Workspace', () => {
  it('menampilkan status belum terhubung dalam bahasa Indonesia', async () => {
    render(<Workspace />)
    expect((await screen.findAllByText('Belum terhubung')).length).toBeGreaterThan(0)
    expect(screen.getByText(/server dan layanan AI belum terhubung/i)).toBeInTheDocument()
  })

  it('menonaktifkan unggah dan tidak menampilkan keberhasilan palsu', async () => {
    const user = userEvent.setup()
    render(<Workspace />)
    const upload = await screen.findByRole('button', { name: /unggah/i })
    expect(upload).toBeDisabled()
    await user.click(upload)
    expect(screen.queryByText(/berhasil|sukses|selesai/i)).not.toBeInTheDocument()
    expect(screen.getByText(/unggah belum tersedia/i)).toBeInTheDocument()
  })

  it('menjelaskan bahwa hasil AI belum tersedia tanpa hasil buatan', async () => {
    render(<Workspace />)
    expect(await screen.findByText('Analisis AI belum tersedia')).toBeInTheDocument()
    expect(screen.getByText(/belum ada hasil untuk ditampilkan/i)).toBeInTheDocument()
  })
})
