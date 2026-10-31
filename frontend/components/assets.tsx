"use client"
import { useRef, useState } from 'react'
import type { WorkspaceItem } from './auth-panel'

type Version = { id: number; status: string; original_sha256: string; output_sha256?: string; job_id?: string; label: string | null }
type Candidate = { id: number; output_sha256: string; prompt?: string | null; size?: string | null }
type Asset = { id: number; status?: string; versions: Version[] }
const options: RequestInit = { credentials: 'include', redirect: 'error', cache: 'no-store', headers: { Accept: 'application/json' } }

export function Assets({ workspaces }: { workspaces: WorkspaceItem[] }) {
  const [workspace, setWorkspace] = useState('')
  const [assets, setAssets] = useState<Asset[] | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [exports, setExports] = useState<Record<number, string>>({})
  const [candidates, setCandidates] = useState<Record<number, Candidate>>({})
  const [prompts, setPrompts] = useState<Record<number, string>>({})
  const [sizes, setSizes] = useState<Record<number, string>>({})
  const [history, setHistory] = useState<Record<number, Candidate[]>>({})
  const [createPrompt, setCreatePrompt] = useState('')
  const [createSize, setCreateSize] = useState('')
  const [analysis, setAnalysis] = useState<Record<number, string>>({})
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [uploadKey, setUploadKey] = useState('')
  const fileInputRef = useRef<HTMLInputElement>(null)
  async function upload() {
    const raw = document.cookie.split('; ').find(item => item.startsWith('XSRF-TOKEN='))?.slice(11)
    let token = ''
    try { token = raw ? decodeURIComponent(raw) : '' } catch { /* invalid cookie */ }
    if (!token) { setError('Cookie CSRF tidak tersedia.'); return }
    if (!selectedFile || !workspace) { setError('Pilih foto dan workspace terlebih dahulu.'); return }
    const key = uploadKey || globalThis.crypto.randomUUID()
    setUploadKey(key); setBusy(true); setError('')
    try {
      const body = new FormData(); body.append('file', selectedFile)
      const response = await fetch(`/workspaces/${workspace}/assets`, { ...options, method: 'POST', body, headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token, 'Idempotency-Key': key } })
      if (!response.ok) throw new Error()
      const { data } = await response.json()
      if (!data || !Number.isSafeInteger(data.id) || typeof data.sha256 !== 'string') throw new Error()
      setAssets(previous => [...(previous || []), { id: data.id, status: 'diunggah', versions: [] }])
    } catch { setError('Permintaan gagal atau hasil tidak dapat dipastikan. Coba unggah kembali dengan tombol yang sama.') }
    finally { setBusy(false) }
  }
  async function processCandidate(asset: Asset, version: Version): Promise<Candidate | null> {
    const prompt = (prompts[version.id] || '').trim()
    const size = sizes[version.id]
    const payload: Record<string, string> = {}
    if (prompt) payload.prompt = prompt
    if (size) payload.size = size
    const response = await fetch(`/workspaces/${workspace}/assets/${asset.id}/versions/${version.id}/processing-jobs`, { ...options, method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': tokenFromCookie() }, body: JSON.stringify(payload) })
    if (!response.ok) return null
    const { data } = await response.json()
    if (!data || !Number.isSafeInteger(data.id) || typeof data.output_sha256 !== 'string') return null
    // Keep every generated candidate so the user can compare variations.
    setHistory(previous => ({ ...previous, [version.id]: [{ id: data.id, output_sha256: data.output_sha256, prompt: data.prompt, size: data.size }, ...(previous[version.id] || [])] }))
    return data
  }
  async function loadHistory(asset: Asset, version: Version) {
    const response = await fetch(`/workspaces/${workspace}/assets/${asset.id}/versions/${version.id}/processing-jobs`, options)
    if (!response.ok) return
    const { data } = await response.json()
    if (Array.isArray(data)) setHistory(previous => ({ ...previous, [version.id]: data }))
  }
  async function createImage() {
    if (!workspace || busy || !createPrompt.trim()) return
    setBusy(true); setError('')
    try {
      const body: Record<string, string> = { prompt: createPrompt.trim() }
      if (createSize) body.size = createSize
      const response = await fetch(`/workspaces/${workspace}/created-images`, { ...options, method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': tokenFromCookie() }, body: JSON.stringify(body) })
      if (!response.ok) { setError(response.status === 502 ? 'Gambar tidak dapat dibuat. Periksa model buat gambar.' : 'Pembuatan gambar gagal.'); return }
      setCreatePrompt('')
      await load()
    } catch { setError('Pembuatan gambar gagal. Periksa sesi dan koneksi.') }
    finally { setBusy(false) }
  }
  async function analyzeImage(asset: Asset) {
    if (busy) return
    setBusy(true); setError('')
    try {
      const response = await fetch(`/workspaces/${workspace}/assets/${asset.id}/vision`, { ...options, method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': tokenFromCookie() }, body: '{}' })
      if (!response.ok) { setError(response.status === 502 ? 'Analisis gagal. Periksa model vision.' : 'Analisis gambar gagal.'); return }
      const { data } = await response.json()
      if (data && typeof data.analysis === 'string') setAnalysis(previous => ({ ...previous, [asset.id]: data.analysis }))
    } catch { setError('Analisis gambar gagal. Periksa sesi dan koneksi.') }
    finally { setBusy(false) }
  }
  function tokenFromCookie() {
    const raw = document.cookie.split('; ').find(item => item.startsWith('XSRF-TOKEN='))?.slice(11)
    try { return raw ? decodeURIComponent(raw) : '' } catch { return '' }
  }
  async function action(asset: Asset, version: Version, kind: 'approval' | 'export') {
    const raw = document.cookie.split('; ').find(item => item.startsWith('XSRF-TOKEN='))?.slice(11)
    let token: string
    try { token = raw ? decodeURIComponent(raw) : '' } catch { token = '' }
    if (!token) { setError('Cookie CSRF tidak tersedia.'); return }
    setBusy(true); setError('')
    try {
      const candidate = kind === 'approval' ? (candidates[version.id] || await processCandidate(asset, version)) : candidates[version.id]
      if (kind === 'approval' && !candidate) throw new Error()
      if (candidate) setCandidates(previous => ({ ...previous, [version.id]: candidate }))
      const response = await fetch(`/workspaces/${workspace}/assets/${asset.id}/versions/${version.id}/${kind}`, { ...options, method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token }, ...(kind === 'approval' && candidate ? { body: JSON.stringify({ job_id: candidate.id, output_sha256: candidate.output_sha256 }) } : {}) })
      if (!response.ok) throw new Error()
      const { data } = await response.json()
      if (kind === 'approval' ? (data?.job_id !== candidate?.id || data?.output_sha256 !== candidate?.output_sha256) : (data?.workspace_id !== Number(workspace) || data?.asset_id !== asset.id || data?.asset_version_id !== version.id || data?.original_sha256 !== version.original_sha256 || typeof data.id !== 'string' || !/^[a-zA-Z0-9-]+$/.test(data.id) || data.artifact_sha256 !== candidate?.output_sha256)) throw new Error()
      setAssets(previous => previous?.map(a => a.id === asset.id ? { ...a, versions: a.versions.map(v => v.id === version.id ? { ...v, status: kind === 'approval' ? 'approved' : 'exported' } : v) } : a) ?? null)
      if (kind === 'export') setExports(previous => ({ ...previous, [version.id]: data.id }))
    } catch { setAssets(null); setError('Permintaan gagal atau hasil tidak dapat dipastikan. Muat ulang aset sebelum mencoba lagi.') }
    finally { setBusy(false) }
  }
  async function createVersion(asset: Asset) {
    const raw = document.cookie.split('; ').find(item => item.startsWith('XSRF-TOKEN='))?.slice(11)
    let token = ''
    try { token = raw ? decodeURIComponent(raw) : '' } catch { /* invalid cookie */ }
    if (!token) { setError('Cookie CSRF tidak tersedia.'); return }
    setBusy(true); setError('')
    try {
      const response = await fetch(`/workspaces/${workspace}/assets/${asset.id}/versions`, { ...options, method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token }, body: '{}' })
      if (!response.ok) throw new Error()
      const { data } = await response.json()
      if (!data || data.asset_id !== asset.id || !Number.isSafeInteger(data.id) || data.status !== 'draft' || typeof data.original_sha256 !== 'string' || data.label !== null) throw new Error()
      setAssets(previous => previous?.map(a => a.id === asset.id ? { ...a, versions: [data] } : a) ?? null)
    } catch { setAssets(null); setError('Permintaan gagal atau hasil tidak dapat dipastikan. Muat ulang aset sebelum mencoba lagi.') }
    finally { setBusy(false) }
  }
  async function load() {
    if (!workspaces.some(item => String(item.id) === workspace)) return
    setBusy(true); setError(''); setAssets(null)
    try {
      const response = await fetch(`/workspaces/${workspace}/assets`, options)
      if (!response.ok) throw new Error()
      const result = await response.json()
      if (!Array.isArray(result.data) || !result.data.every((a: Asset) => Number.isSafeInteger(a.id) && Array.isArray(a.versions) && a.versions.every(v => Number.isSafeInteger(v.id) && typeof v.status === 'string' && typeof v.original_sha256 === 'string' && (v.label === null || typeof v.label === 'string')))) throw new Error()
      setAssets(result.data)
    } catch { setError('Aset tidak dapat dimuat. Periksa sesi dan koneksi.') }
    finally { setBusy(false) }
  }
  return <section className="card"><h2>Aset dan versi</h2>
    <label>Workspace aset<select value={workspace} disabled={busy} onChange={e => { setWorkspace(e.target.value); setAssets(null); setExports({}); setError('') }}><option value="">Pilih workspace</option>{workspaces.map(w => <option key={w.id} value={w.id}>{w.name}</option>)}</select></label>
    {workspaces.length > 0 ? <>
      <input ref={fileInputRef} id="food-photo" aria-label="Foto makanan" type="file" accept="image/png,image/jpeg" disabled={!workspace || busy} onChange={e => { setSelectedFile(e.target.files?.[0] || null); setUploadKey(''); setError('') }} />
      <button type="button" disabled={!workspace || !selectedFile || busy} onClick={upload}>Unggah foto</button>
    </> : <button type="button" disabled>Unggah foto</button>}
    <button type="button" disabled={!workspace || busy} onClick={load}>Muat aset</button>
    {workspaces.length > 0 && <div className="create-panel"><h3>Buat gambar dari teks</h3><label className="prompt-field">Deskripsi gambar<textarea value={createPrompt} maxLength={2000} disabled={!workspace || busy} placeholder="mis. nasi goreng seafood di piring hitam, cahaya studio, latar gelap" onChange={e => setCreatePrompt(e.target.value)} /></label> <label className="size-field">Ukuran<select value={createSize} disabled={busy} onChange={e => setCreateSize(e.target.value)}><option value="">Default</option><option value="512x512">512×512</option><option value="1024x1024">1024×1024</option></select></label> <button type="button" disabled={!workspace || busy || !createPrompt.trim()} onClick={createImage}>Buat gambar</button></div>}
    {error && <p role="alert">{error}</p>}
    {assets && (assets.length ? <ul>{assets.map(a => <li key={a.id}><span>Aset #{a.id} — {a.status || 'committed'}</span>
      <div className="image-preview"><img src={`/workspaces/${workspace}/assets/${a.id}/image`} alt={`Foto asli Aset #${a.id}`} loading="lazy" /></div>
      <button type="button" disabled={busy} onClick={() => analyzeImage(a)}>Analisis foto (vision)</button>
      {analysis[a.id] && <p className="vision-output">{analysis[a.id]}</p>}
      {a.versions.length === 0 && <button type="button" disabled={busy} onClick={() => createVersion(a)}>Buat versi Aset #{a.id}</button>}<ul>{a.versions.map(v => <li key={v.id}>{v.label || `Versi #${v.id}`} — {v.status}
        {v.status === 'draft' && <><label className="prompt-field">Instruksi edit AI (opsional)<textarea value={prompts[v.id] || ''} maxLength={2000} disabled={busy} placeholder="mis. latar kayu rustic, cahaya hangat, uap mengepul" onChange={e => setPrompts(previous => ({ ...previous, [v.id]: e.target.value }))} /></label> <label className="size-field">Ukuran<select value={sizes[v.id] || ''} disabled={busy} onChange={e => setSizes(previous => ({ ...previous, [v.id]: e.target.value }))}><option value="">Default</option><option value="512x512">512×512</option><option value="1024x1024">1024×1024</option></select></label> <button type="button" disabled={busy} onClick={() => action(a, v, 'approval')}>Proses & setujui {v.label || `Versi #${v.id}`}</button> <button type="button" disabled={busy} onClick={() => loadHistory(a, v)}>Muat variasi</button></>}
        {history[v.id] && history[v.id].length > 0 && <div className="variation-grid">{history[v.id].map(c => <figure key={c.id} className="image-preview"><img src={`/workspaces/${workspace}/assets/${a.id}/processing-jobs/${c.id}/output`} alt={`Variasi #${c.id}`} loading="lazy" /><figcaption>{c.size || 'default'}{c.prompt ? ` — ${c.prompt}` : ''}</figcaption></figure>)}</div>}
        {candidates[v.id] && <div className="image-preview"><img src={`/workspaces/${workspace}/assets/${a.id}/processing-jobs/${candidates[v.id].id}/output`} alt={`Hasil AI ${v.label || `Versi #${v.id}`}`} loading="lazy" /></div>}
        {v.status === 'approved' && <button type="button" disabled={busy} onClick={() => action(a, v, 'export')}>Ekspor {v.label || `Versi #${v.id}`}</button>} {exports[v.id] && <a href={`/workspaces/${workspace}/exports/${exports[v.id]}`}>Unduh {v.label || `Versi #${v.id}`}</a>}</li>)}</ul></li>)}</ul> : <p>Belum ada aset.</p>)}
  </section>
}
