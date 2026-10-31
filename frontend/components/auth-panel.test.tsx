import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi } from 'vitest'
import { AuthPanel } from './auth-panel'

const json = (data: unknown, status = 200) => new Response(JSON.stringify(data), { status })
afterEach(() => { vi.restoreAllMocks(); document.cookie = 'XSRF-TOKEN=; Max-Age=0' })

describe('status sesi dan login', () => {
  it('menampilkan sesi belum terhubung setelah bootstrap guest tanpa mengirim kredensial', async () => {
    const fetcher = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 })).mockResolvedValueOnce(json({}, 401))
    render(<AuthPanel />)
    expect(await screen.findByText('Sesi belum terhubung')).toBeInTheDocument()
    expect(fetcher.mock.calls.map(([url]) => url)).toEqual(['/csrf-cookie', '/session'])
  })
  it('login dan logout memakai CSRF tanpa menyimpan key', async () => {
    const user = userEvent.setup(); document.cookie = 'XSRF-TOKEN=csrf%2Bvalue'
    const fetcher = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 })).mockResolvedValueOnce(json({}, 401)).mockResolvedValueOnce(json({ user: { id: 1, email: 'a@b.test' } })).mockResolvedValueOnce(new Response(null, { status: 204 }))
    const storage = vi.spyOn(Storage.prototype, 'setItem')
    render(<AuthPanel />)
    await user.type(await screen.findByLabelText('Email'), 'a@b.test'); await user.type(screen.getByLabelText('Password'), 'secret')
    await user.click(screen.getByRole('button', { name: 'Login' }))
    await screen.findByText('Sesi aktif')
    expect(fetcher).toHaveBeenNthCalledWith(3, '/login', expect.objectContaining({ method: 'POST', credentials: 'include', headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'csrf+value' }), body: JSON.stringify({ email: 'a@b.test', password: 'secret' }) }))
    await user.click(screen.getByRole('button', { name: 'Logout' }))
    await waitFor(() => expect(screen.getByText('Sesi belum terhubung')).toBeInTheDocument())
    expect(fetcher).toHaveBeenNthCalledWith(4, '/logout', expect.objectContaining({ method: 'POST', credentials: 'include', headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'csrf+value' }) }))
    expect(storage).not.toHaveBeenCalled()
  })
})
