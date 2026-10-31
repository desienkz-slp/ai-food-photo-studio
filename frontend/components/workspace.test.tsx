import { render, screen } from '@testing-library/react'
import { vi } from 'vitest'
import { Workspace } from './workspace'

// The workspace is gated behind login; simulate an authenticated session so
// its content renders. History starts empty so no network coupling.
const json = (data: unknown, status = 200) => new Response(JSON.stringify(data), { status })
beforeEach(() => {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (url) => {
    if (url === '/csrf-cookie') return new Response(null, { status: 204 })
    if (url === '/session') return json({ user: { id: 1, email: 'a@b.test' } })
    if (url === '/workspaces') return json({ data: [] })
    if (url === '/photo-edit/history') return json({ data: [] })
    return new Response(null, { status: 404 })
  })
})
afterEach(() => vi.restoreAllMocks())

describe('Workspace', () => {
  it('menampilkan panel editor foto AI', async () => {
    render(<Workspace />)
    expect(await screen.findByRole('heading', { name: 'Edit foto (AI)' })).toBeInTheDocument()
  })

  it('menonaktifkan tombol kirim edit sebelum ada foto dan instruksi', async () => {
    render(<Workspace />)
    expect(await screen.findByRole('button', { name: /kirim edit/i })).toBeDisabled()
  })
})
