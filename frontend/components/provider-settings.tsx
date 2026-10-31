"use client"

import { useRef, useState } from "react"

type Provider = { type: string; communication_mode?: string; model: string; name?: string; endpoint?: string }

function csrfToken() {
  const value = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length)
  if (!value) return null
  try { return decodeURIComponent(value) || null } catch { return null }
}

// Global provider settings, managed by the super admin and shared by every
// tenant. No workspace scope.
export function ProviderSettings() {
  const [providers, setProviders] = useState<Provider[] | null>(null)
  const [type, setType] = useState('')
  const [mode, setMode] = useState('chat')
  const [name, setName] = useState('')
  const [endpoint, setEndpoint] = useState('')
  const [model, setModel] = useState('')
  const [modelOptions, setModelOptions] = useState<string[]>([])
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const keyRef = useRef<HTMLInputElement>(null)
  const url = '/providers'

  function failure(status: number) {
    if (status === 401 || status === 419) return 'Sesi tidak tersedia atau kedaluwarsa. Login diperlukan.'
    if (status === 403) return 'Hanya super admin yang dapat mengelola provider.'
    if (status === 422) return 'Pengaturan tidak valid. Periksa tipe, model, dan API key.'
    return 'Permintaan gagal. Periksa koneksi dan coba lagi.'
  }

  async function load() {
    if (busy) return
    setBusy(true); setError(''); setMessage(''); setProviders(null)
    try {
      const response = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' }, redirect: 'error', cache: 'no-store' })
      if (!response.ok) { setError(failure(response.status)); return }
      const result = await response.json()
      if (!Array.isArray(result.data) || !result.data.every((item: Provider) => typeof item.type === 'string' && typeof item.model === 'string')) throw new Error()
      setProviders(result.data.map((item: Provider) => ({type: item.type, model: item.model})))
      setMessage('Pengaturan provider dimuat.')
    } catch { setError('Sesi atau koneksi tidak tersedia. Pengaturan tidak dapat dimuat.') }
    finally { setBusy(false) }
  }

  async function loadModels() {
    if (busy || !type) return
    setBusy(true); setError(''); setMessage('')
    try {
      const response = await fetch(`/providers/${type}/models`, { credentials: 'include', headers: { Accept: 'application/json' }, redirect: 'error', cache: 'no-store' })
      if (!response.ok) { setError(response.status === 502 ? 'Daftar model tidak dapat dimuat dari provider.' : failure(response.status)); return }
      const result = await response.json()
      if (!Array.isArray(result.data) || !result.data.every((item: unknown) => typeof item === 'string')) throw new Error()
      setModelOptions(result.data)
      setMessage(result.data.length ? `${result.data.length} model dimuat.` : 'Provider tidak mengembalikan model.')
    } catch { setError('Daftar model tidak dapat dimuat. Periksa endpoint dan API key tersimpan.') }
    finally { setBusy(false) }
  }

  async function save(event: React.FormEvent) {
    event.preventDefault()
    if (!providers || busy || !type || !model.trim()) return
    const token = csrfToken()
    if (!token) { setError('Cookie CSRF tidak tersedia. Sesi diperlukan sebelum menyimpan.'); return }
    const key = keyRef.current?.value || ''
    if (!key && !providers.some((provider) => provider.type === type)) { setError('API key diperlukan untuk provider baru.'); return }
    setBusy(true); setError(''); setMessage('')
    try {
      const response = await fetch(url, { method: 'POST', credentials: 'include', redirect: 'error', cache: 'no-store', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token }, body: JSON.stringify({ type, communication_mode: mode, model: model.trim(), ...(name.trim() ? { name: name.trim() } : {}), ...(endpoint.trim() ? { endpoint: endpoint.trim() } : {}), ...(key ? {api_key: key} : {}) }) })
      if (!response.ok) { if (response.status === 401 || response.status === 403 || response.status === 419) setProviders(null); setError(failure(response.status)); return }
      const result = await response.json()
      if (result.data?.type !== type || result.data?.model !== model.trim()) throw new Error()
      setProviders((previous) => [...(previous || []).filter((provider) => provider.type !== type), {type, model: model.trim()}])
      setMessage('Pengaturan provider tersimpan.')
    } catch { setProviders(null); setError('Hasil penyimpanan tidak dapat dipastikan. Muat ulang pengaturan sebelum mencoba lagi.') }
    finally { if (keyRef.current) keyRef.current.value = ''; setBusy(false) }
  }

  return <section className="card provider-settings" aria-labelledby="provider-heading">
    <h2 id="provider-heading">Pengaturan provider</h2>
    <p>Konfigurasi provider bersifat global dan dipakai semua tenant. Hanya super admin yang dapat mengubahnya.</p>
    <button type="button" disabled={busy} onClick={load}>{busy ? 'Memuat…' : 'Muat pengaturan'}</button>
    {message && <p role="status">{message}</p>}{error && <p role="alert">{error}</p>}
    <form onSubmit={save} autoComplete="off">
      <label>Nama provider<input value={name} onChange={(event) => setName(event.target.value)} maxLength={100} /></label>
      <label>Endpoint HTTPS<input type="url" value={endpoint} onChange={(event) => setEndpoint(event.target.value)} maxLength={2048} /></label>
      <label>Tipe provider<select value={type} onChange={(event) => setType(event.target.value)}><option value="">Pilih tipe provider</option><option value="openai-compatible">Kompatibel OpenAI</option><option value="other">Lainnya</option></select></label>
      <label>Tipe komunikasi<select value={mode} onChange={(event) => setMode(event.target.value)}><option value="chat">Chat</option><option value="message">Message</option><option value="response">Response</option></select></label>
      <label>API key<input ref={keyRef} name="api-key" type="password" autoComplete="new-password" maxLength={4096} /></label>
      <label>Model manual<input value={model} onChange={(event) => setModel(event.target.value)} maxLength={255} list="provider-model-options" /></label>
      {modelOptions.length > 0 && <datalist id="provider-model-options">{modelOptions.map((item) => <option key={item} value={item} />)}</datalist>}
      <p>Klik “Muat model” untuk mengambil daftar dari provider (butuh pengaturan tersimpan). Anda tetap boleh mengetik model manual. API key kosong mempertahankan key sebelumnya.</p>
      <div className="provider-actions"><button type="button" disabled={busy || !type || !providers?.some((provider) => provider.type === type)} onClick={loadModels}>{busy ? 'Memuat…' : 'Muat model'}</button><button type="submit" disabled={!providers || busy}>{busy ? 'Memproses…' : 'Simpan pengaturan'}</button></div>
    </form>
  </section>
}
