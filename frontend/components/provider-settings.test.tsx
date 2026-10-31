import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi } from 'vitest'
import { ProviderSettings } from './provider-settings'

const json = (data: unknown, status = 200) => new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } })

afterEach(() => { vi.restoreAllMocks(); document.cookie = 'XSRF-TOKEN=; Max-Age=0' })

describe('Pengaturan provider', () => {
  it('gagal tertutup sebelum pengaturan dimuat', async () => {
    const fetcher = vi.spyOn(globalThis, 'fetch')
    render(<ProviderSettings />)
    expect(screen.getByRole('button', { name: 'Simpan pengaturan' })).toBeDisabled()
    expect(fetcher).not.toHaveBeenCalled()
    expect(screen.getByText(/global/i)).toBeInTheDocument()
  })

  it('mengirim hanya kontrak backend global dengan cookie CSRF dan kredensial', async () => {
    const user = userEvent.setup()
    document.cookie = 'XSRF-TOKEN=token%2Bvalue'
    const fetcher = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({data: []})).mockResolvedValueOnce(json({data: {type: 'other', model: 'm1'}}, 201))
    const storage = vi.spyOn(Storage.prototype, 'setItem')
    const log = vi.spyOn(console, 'log')
    render(<ProviderSettings />)
    await user.click(screen.getByRole('button', {name: 'Muat pengaturan'}))
    await screen.findByText(/pengaturan provider dimuat/i)
    await user.selectOptions(screen.getByLabelText('Tipe provider'), 'other')
    await user.type(screen.getByLabelText('Model manual'), 'm1')
    await user.type(screen.getByLabelText('API key'), 'secret-test-key')
    await user.click(screen.getByRole('button', {name: 'Simpan pengaturan'}))
    await screen.findByText(/pengaturan provider tersimpan/i)
    expect(fetcher).toHaveBeenLastCalledWith('/providers', expect.objectContaining({method: 'POST', credentials: 'include', headers: expect.objectContaining({'X-XSRF-TOKEN': 'token+value'}), body: JSON.stringify({type: 'other', communication_mode: 'chat', model: 'm1', api_key: 'secret-test-key'})}))
    expect(screen.getByLabelText('API key')).toHaveValue('')
    expect(storage).not.toHaveBeenCalled()
    expect(log).not.toHaveBeenCalled()
  })

  it('tidak mengirim key jika cookie CSRF tidak tersedia', async () => {
    const user = userEvent.setup()
    const fetcher = vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({data: []}))
    render(<ProviderSettings />)
    await user.click(screen.getByRole('button', {name: 'Muat pengaturan'}))
    await screen.findByText(/pengaturan provider dimuat/i)
    await user.selectOptions(screen.getByLabelText('Tipe provider'), 'other')
    await user.type(screen.getByLabelText('Model manual'), 'm1')
    await user.type(screen.getByLabelText('API key'), 'secret-test-key')
    await user.click(screen.getByRole('button', {name: 'Simpan pengaturan'}))
    expect(screen.getByRole('alert')).toHaveTextContent(/CSRF/i)
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('menolak menyimpan saat sesi tidak sah', async () => {
    const user = userEvent.setup()
    const fetcher = vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({}, 401))
    render(<ProviderSettings />)
    await user.click(screen.getByRole('button', {name: 'Muat pengaturan'}))
    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/sesi/i))
    expect(screen.getByRole('button', {name: 'Simpan pengaturan'})).toBeDisabled()
    expect(fetcher).toHaveBeenCalledTimes(1)
  })
})
