import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi } from 'vitest'
import { Workspace } from './workspace'

const json = (data: unknown, status = 200) => new Response(JSON.stringify(data), { status })
afterEach(() => { vi.restoreAllMocks(); document.cookie = 'XSRF-TOKEN=; Max-Age=0' })

it('memulihkan sesi dan memilih workspace dari API', async () => {
  const user = userEvent.setup()
  const fetcher = vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    if (input === '/csrf-cookie') return new Response(null, { status: 204 })
    if (input === '/session') return json({ user: { id: 1, name: 'A', email: 'a@example.test', is_super_admin: true } })
    if (input === '/workspaces') return json({ data: [{ id: 42, name: 'Dapur' }] })
    if (input === '/photo-edit/history') return json({ data: [] })
    if (input === '/providers') return json({ data: [] })
    throw new Error(`Unexpected request ${input}`)
  })
  render(<Workspace />)
  expect(await screen.findByText('Sesi aktif')).toBeInTheDocument()
  // Provider panel is global and only shown to the super admin.
  await user.click(await screen.findByRole('button', { name: 'Muat pengaturan' }))
  expect(await screen.findByText('Pengaturan provider dimuat.')).toBeInTheDocument()
  expect(fetcher).toHaveBeenCalledWith('/csrf-cookie', expect.objectContaining({ credentials: 'include' }))
  expect(fetcher).toHaveBeenCalledWith('/session', expect.objectContaining({ credentials: 'include' }))
  expect(fetcher).toHaveBeenCalledWith('/workspaces', expect.objectContaining({ credentials: 'include' }))
  expect(fetcher).toHaveBeenCalledWith('/providers', expect.objectContaining({ credentials: 'include' }))
})
