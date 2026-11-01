"use client"

import { useRef, useState } from "react"

function tokenFromCookie() {
  const raw = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length)
  try { return raw ? decodeURIComponent(raw) : '' } catch { return '' }
}

type HistoryItem = { id: number; instruction: string; created_at?: string }

// Preset editing goals shown as checkboxes; the vision model turns the selected
// ones + the user's note into one refined instruction.
const TEMPLATES = [
  'Ganti/bersihkan latar belakang jadi rapi',
  'Cahaya studio hangat & merata',
  'Tambah uap mengepul (kesan panas)',
  'Tingkatkan warna & saturasi makanan',
  'Pertajam detail & tekstur',
  'Tambah garnish segar (daun/cabai)',
  'Buang objek pengganggu di sekitar',
  'Gaya flat-lay top-down profesional',
]

/**
 * ChatGPT-style photo editor: upload a photo, pick template goals and/or type a
 * note, optionally let the vision model refine it into a strong instruction,
 * then edit. Results are persisted server-side as downloadable history.
 */
export function PhotoEditor() {
  const fileRef = useRef<HTMLInputElement>(null)
  const [inputImage, setInputImage] = useState('')
  const [instruction, setInstruction] = useState('')
  const [picked, setPicked] = useState<string[]>([])
  const [latest, setLatest] = useState('')
  const [history, setHistory] = useState<HistoryItem[]>([])
  const [busy, setBusy] = useState(false)
  const [enhancing, setEnhancing] = useState(false)
  const [showHistory, setShowHistory] = useState(false)
  const [viewId, setViewId] = useState<number | null>(null)
  const [error, setError] = useState('')

  async function loadHistory() {
    try {
      const response = await fetch('/photo-edit/history', { credentials: 'include', headers: { Accept: 'application/json' }, cache: 'no-store' })
      if (!response.ok) return
      const result = await response.json()
      if (Array.isArray(result?.data)) setHistory(result.data)
    } catch { /* history is non-critical; ignore load errors */ }
  }

  async function toggleHistory() {
    const next = !showHistory
    setShowHistory(next)
    if (next) await loadHistory()
  }

  async function removeHistory(id: number) {
    const token = tokenFromCookie()
    if (!token) { setError('Cookie CSRF tidak tersedia. Muat ulang halaman.'); return }
    try {
      const response = await fetch(`/photo-edit/history/${id}`, { method: 'DELETE', credentials: 'include', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token }, cache: 'no-store' })
      if (!response.ok) { setError('Gagal menghapus riwayat.'); return }
      setHistory((prev) => prev.filter((item) => item.id !== id))
      if (viewId === id) setViewId(null)
    } catch { setError('Gagal menghapus riwayat.') }
  }


  function pickFile(file: File | null) {
    setError('')
    if (!file) { setInputImage(''); return }
    const reader = new FileReader()
    reader.onload = () => setInputImage(typeof reader.result === 'string' ? reader.result : '')
    reader.onerror = () => setError('Foto tidak dapat dibaca.')
    reader.readAsDataURL(file)
  }

  function toggle(template: string) {
    setPicked((prev) => prev.includes(template) ? prev.filter((t) => t !== template) : [...prev, template])
  }

  async function buildForm() {
    const blob = await (await fetch(inputImage)).blob()
    const form = new FormData()
    form.append('image', blob, 'photo')
    return form
  }

  async function enhance() {
    if (enhancing || busy || !inputImage || (picked.length === 0 && !instruction.trim())) return
    const token = tokenFromCookie()
    if (!token) { setError('Cookie CSRF tidak tersedia. Muat ulang halaman.'); return }
    setEnhancing(true); setError('')
    try {
      const form = await buildForm()
      if (instruction.trim()) form.append('instruction', instruction.trim())
      picked.forEach((t) => form.append('templates[]', t))
      const response = await fetch('/photo-edit/enhance', { method: 'POST', credentials: 'include', redirect: 'error', cache: 'no-store', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token }, body: form })
      if (!response.ok) { setError(response.status === 502 ? 'Prompt tidak dapat dikembangkan. Periksa model vision.' : 'Gagal mengembangkan prompt. Periksa sesi dan koneksi.'); return }
      const result = await response.json()
      const refined = result?.data?.instruction
      if (typeof refined === 'string' && refined.trim()) setInstruction(refined.trim())
      else setError('Model tidak mengembalikan prompt.')
    } catch { setError('Gagal mengembangkan prompt. Periksa sesi dan koneksi.') }
    finally { setEnhancing(false) }
  }

  async function submit() {
    if (busy || !inputImage || !instruction.trim()) return
    const token = tokenFromCookie()
    if (!token) { setError('Cookie CSRF tidak tersedia. Muat ulang halaman.'); return }
    setBusy(true); setError('')
    try {
      const form = await buildForm()
      form.append('instruction', instruction.trim())
      const response = await fetch('/photo-edit', { method: 'POST', credentials: 'include', redirect: 'error', cache: 'no-store', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token }, body: form })
      if (!response.ok) { setError(response.status === 502 ? 'Foto tidak dapat diedit. Periksa model edit gambar.' : (response.status === 422 ? 'Foto atau instruksi tidak valid.' : 'Pengeditan gagal. Periksa sesi dan koneksi.')); return }
      const result = await response.json()
      const image = result?.data?.image
      if (typeof image !== 'string' || !image.startsWith('data:image/')) { setError('Provider tidak mengembalikan gambar.'); return }
      setLatest(image)
      setInputImage(image)   // chain: result becomes next input for iterative edits
      setInstruction('')
      await loadHistory()
    } catch { setError('Pengeditan gagal. Periksa sesi dan koneksi.') }
    finally { setBusy(false) }
  }

  function reset() {
    setLatest(''); setInputImage(''); setInstruction(''); setPicked([]); setError('')
    if (fileRef.current) fileRef.current.value = ''
  }

  const locked = busy || enhancing
  return <section className="card photo-editor" aria-labelledby="editor-heading">
    <h2 id="editor-heading">Edit foto (AI)</h2>
    <p>Unggah foto, pilih template tujuan, lalu kembangkan prompt dengan AI atau langsung kirim. Hasil tersimpan di riwayat untuk diunduh.</p>
    <label>Foto<input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" disabled={locked} onChange={(event) => pickFile(event.target.files?.[0] || null)} /></label>
    {inputImage && <div className="image-preview"><img src={inputImage} alt="Foto saat ini" loading="lazy" /></div>}

    <fieldset className="edit-templates" disabled={locked}>
      <legend>Template tujuan</legend>
      {TEMPLATES.map((template) => <label key={template} className="template-option">
        <input type="checkbox" checked={picked.includes(template)} onChange={() => toggle(template)} />
        <span>{template}</span>
      </label>)}
    </fieldset>

    <label className="prompt-field">Instruksi edit<textarea value={instruction} maxLength={2000} disabled={locked} placeholder="mis. ganti latar jadi kayu rustic, cahaya hangat, tambah uap mengepul" onChange={(event) => setInstruction(event.target.value)} /></label>
    {error && <p role="alert">{error}</p>}
    <div className="provider-actions">
      <button type="button" disabled={locked || !inputImage || (picked.length === 0 && !instruction.trim())} onClick={enhance}>{enhancing ? 'Mengembangkan…' : 'Kembangkan prompt (AI)'}</button>
      <button type="button" disabled={locked || !inputImage || !instruction.trim()} onClick={submit}>{busy ? 'Mengedit…' : 'Kirim edit'}</button>
      {(latest || inputImage) && <button type="button" disabled={locked} onClick={reset}>Mulai baru</button>}
      <button type="button" className="ghost" aria-expanded={showHistory} onClick={toggleHistory}>History</button>
    </div>

    {showHistory && <div className="edit-history">
      <p className="history-note">Riwayat tersimpan 7 hari.</p>
      {history.length === 0
        ? <p>Belum ada riwayat.</p>
        : <ul className="history-list">{history.map((item) => <li key={item.id} className="history-row">
            <span className="history-text" title={item.instruction}>{item.instruction}</span>
            <span className="history-actions">
              <button type="button" className="link" onClick={() => setViewId(viewId === item.id ? null : item.id)}>{viewId === item.id ? 'Tutup' : 'Lihat'}</button>
              <a href={`/photo-edit/history/${item.id}/download`}>Unduh</a>
              <button type="button" className="link danger" onClick={() => removeHistory(item.id)}>Hapus</button>
            </span>
            {viewId === item.id && <div className="image-preview"><img src={`/photo-edit/history/${item.id}/download`} alt={`Hasil edit ${item.id}`} loading="lazy" /></div>}
          </li>)}</ul>}
    </div>}
  </section>
}
