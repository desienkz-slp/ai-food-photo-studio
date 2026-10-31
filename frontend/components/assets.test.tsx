import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Assets } from './assets'

it('loads workspace assets and displays version status', async () => {
  const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: [{ id: 7, versions: [{ id: 9, status: 'draft', original_sha256: 'abc', label: 'Foto' }] }] }) })
  vi.stubGlobal('fetch', fetchMock)
  const user = userEvent.setup()
  render(<Assets workspaces={[{ id: 1, name: 'Studio' }]} />)
  await user.selectOptions(screen.getByLabelText('Workspace aset'), '1')
  await user.click(screen.getByRole('button', { name: 'Muat aset' }))
  expect(await screen.findByText(/Foto.*draft/)).toBeInTheDocument()
  expect(fetchMock).toHaveBeenCalledWith('/workspaces/1/assets', expect.objectContaining({ credentials: 'include' }))
  vi.unstubAllGlobals()
})

it('approves a draft and exports the approved version', async () => {
  document.cookie = 'XSRF-TOKEN=token'
  const fetchMock = vi.fn().mockResolvedValueOnce({ ok: true, json: async () => ({ data: [{ id: 7, versions: [{ id: 9, status: 'draft', original_sha256: 'abc', label: 'Foto' }] }] }) }).mockResolvedValueOnce({ ok: true, json: async () => ({ data: { id: 11, output_sha256: 'candidate-digest' } }) }).mockResolvedValueOnce({ ok: true, json: async () => ({ data: { job_id: 11, output_sha256: 'candidate-digest' } }) }).mockResolvedValueOnce({ ok: true, json: async () => ({ data: { id: 'export-1', workspace_id: 1, asset_id: 7, asset_version_id: 9, original_sha256: 'abc', artifact_sha256: 'candidate-digest' } }) })
  // Approval first creates a processing job, then submits its immutable digest.
  vi.stubGlobal('fetch', fetchMock)
  const user = userEvent.setup()
  render(<Assets workspaces={[{ id: 1, name: 'Studio' }]} />)
  await user.selectOptions(screen.getByLabelText('Workspace aset'), '1')
  await user.click(screen.getByRole('button', { name: 'Muat aset' }))
  await user.click(await screen.findByRole('button', { name: 'Setujui Foto' }))
  expect(fetchMock).toHaveBeenNthCalledWith(2, '/workspaces/1/assets/7/versions/9/processing-jobs', expect.objectContaining({ method: 'POST' }))
  expect(fetchMock).toHaveBeenNthCalledWith(3, '/workspaces/1/assets/7/versions/9/approval', expect.objectContaining({ method: 'POST', body: JSON.stringify({ job_id: 11, output_sha256: 'candidate-digest' }), headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'token' }) }))
  await user.click(await screen.findByRole('button', { name: 'Ekspor Foto' }))
  expect(fetchMock).toHaveBeenNthCalledWith(4, '/workspaces/1/assets/7/versions/9/export', expect.objectContaining({ method: 'POST' }))
  expect(await screen.findByRole('link', { name: 'Unduh Foto' })).toHaveAttribute('href', '/workspaces/1/exports/export-1')
  vi.unstubAllGlobals()
})


it('rejects approval responses from a different workspace', async () => {
  document.cookie = 'XSRF-TOKEN=token'
  vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce({ ok: true, json: async () => ({ data: [{ id: 7, versions: [{ id: 9, status: 'draft', original_sha256: 'abc', label: 'Foto' }] }] }) }).mockResolvedValueOnce({ ok: true, json: async () => ({ data: { workspace_id: 2, asset_id: 7, asset_version_id: 9, original_sha256: 'abc' } }) }))
  const user = userEvent.setup()
  render(<Assets workspaces={[{ id: 1, name: 'Studio' }]} />)
  await user.selectOptions(screen.getByLabelText('Workspace aset'), '1')
  await user.click(screen.getByRole('button', { name: 'Muat aset' }))
  await user.click(await screen.findByRole('button', { name: 'Setujui Foto' }))
  expect(await screen.findByRole('alert')).toHaveTextContent('hasil tidak dapat dipastikan')
  expect(screen.queryByRole('button', { name: 'Ekspor Foto' })).not.toBeInTheDocument()
  vi.unstubAllGlobals()
})

it('creates a draft version for a committed asset without versions', async () => {
  document.cookie = 'XSRF-TOKEN=token'
  const fetchMock = vi.fn().mockResolvedValueOnce({ ok: true, json: async () => ({ data: [{ id: 7, status: 'committed', versions: [] }] }) }).mockResolvedValueOnce({ ok: true, json: async () => ({ data: { id: 9, asset_id: 7, status: 'draft', original_sha256: 'abc', label: null } }) })
  vi.stubGlobal('fetch', fetchMock)
  const user = userEvent.setup()
  render(<Assets workspaces={[{ id: 1, name: 'Studio' }]} />)
  await user.selectOptions(screen.getByLabelText('Workspace aset'), '1')
  await user.click(screen.getByRole('button', { name: 'Muat aset' }))
  expect(await screen.findByText(/Aset #7.*committed/)).toBeInTheDocument()
  await user.click(screen.getByRole('button', { name: 'Buat versi Aset #7' }))
  expect(fetchMock).toHaveBeenNthCalledWith(2, '/workspaces/1/assets/7/versions', expect.objectContaining({ method: 'POST', body: '{}' }))
  expect(await screen.findByText(/Versi #9.*draft/)).toBeInTheDocument()
  vi.unstubAllGlobals()
})


it('uploads a selected file with workspace, CSRF, and a stable retry key', async () => {
  document.cookie = 'XSRF-TOKEN=token'
  const fetchMock = vi.fn().mockRejectedValueOnce(new Error('private server detail')).mockResolvedValueOnce({ ok: true, json: async () => ({ data: { id: 7, sha256: 'a'.repeat(64) } }) })
  vi.stubGlobal('fetch', fetchMock)
  const user = userEvent.setup()
  render(<Assets workspaces={[{ id: 1, name: 'Studio' }]} />)
  await user.selectOptions(screen.getByLabelText('Workspace aset'), '1')
  const file = new File(['photo'], 'food.png', { type: 'image/png' })
  await user.upload(screen.getByLabelText('Foto makanan'), file)
  await user.click(screen.getByRole('button', { name: 'Unggah foto' }))
  expect(await screen.findByRole('alert')).not.toHaveTextContent('private server detail')
  await user.click(screen.getByRole('button', { name: 'Unggah foto' }))
  expect(await screen.findByText(/Aset #7.*diunggah/)).toBeInTheDocument()
  const first = fetchMock.mock.calls[0][1]; const second = fetchMock.mock.calls[1][1]
  expect(fetchMock.mock.calls[0][0]).toBe('/workspaces/1/assets')
  expect(first.headers['X-XSRF-TOKEN']).toBe('token')
  expect(first.headers['Idempotency-Key']).toMatch(/^[A-Za-z0-9._~-]+$/)
  expect(second.headers['Idempotency-Key']).toBe(first.headers['Idempotency-Key'])
  expect(first.body.get('file')).toBe(file)
  vi.unstubAllGlobals()
})
