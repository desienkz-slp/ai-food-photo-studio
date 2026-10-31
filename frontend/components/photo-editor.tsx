"use client"

import { useRef, useState } from "react"

function tokenFromCookie() {
  const raw = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length)
  try { return raw ? decodeURIComponent(raw) : '' } catch { return '' }
}

type Turn = { instruction: string; image: string }

/**
 * ChatGPT-style stateless photo editor: upload a photo, type an instruction,
 * get an edited image back inline. Each result can be fed back as the next
 * input for iterative edits. Nothing is persisted on the server.
 */
export function PhotoEditor() {
  const fileRef = useRef<HTMLInputElement>(null)
  const [inputImage, setInputImage] = useState('')
  const [instruction, setInstruction] = useState('')
  const [turns, setTurns] = useState<Turn[]>([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  function pickFile(file: File | null) {
    setError('')
    if (!file) { setInputImage(''); return }
    const reader = new FileReader()
    reader.onload = () => setInputImage(typeof reader.result === 'string' ? reader.result : '')
    reader.onerror = () => setError('Foto tidak dapat dibaca.')
    reader.readAsDataURL(file)
  }

  async function dataUriToBlob(uri: string): Promise<Blob> {
    const response = await fetch(uri)
    return response.blob()
  }

  async function submit() {
    if (busy || !inputImage || !instruction.trim()) return
    const token = tokenFromCookie()
    if (!token) { setError('Cookie CSRF tidak tersedia. Muat ulang halaman.'); return }
    setBusy(true); setError('')
    try {
      const blob = await dataUriToBlob(inputImage)
      const form = new FormData()
      form.append('image', blob, 'photo')
      form.append('instruction', instruction.trim())
      const response = await fetch('/photo-edit', { method: 'POST', credentials: 'include', redirect: 'error', cache: 'no-store', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token }, body: form })
      if (!response.ok) { setError(response.status === 502 ? 'Foto tidak dapat diedit. Periksa model edit gambar.' : (response.status === 422 ? 'Foto atau instruksi tidak valid.' : 'Pengeditan gagal. Periksa sesi dan koneksi.')); return }
      const result = await response.json()
      const image = result?.data?.image
      if (typeof image !== 'string' || !image.startsWith('data:image/')) { setError('Provider tidak mengembalikan gambar.'); return }
      setTurns((previous) => [...previous, { instruction: instruction.trim(), image }])
      setInputImage(image)   // chain: result becomes next input for iterative edits
      setInstruction('')
    } catch { setError('Pengeditan gagal. Periksa sesi dan koneksi.') }
    finally { setBusy(false) }
  }

  function reset() {
    setTurns([]); setInputImage(''); setInstruction(''); setError('')
    if (fileRef.current) fileRef.current.value = ''
  }

  return <section className="card photo-editor" aria-labelledby="editor-heading">
    <h2 id="editor-heading">Edit foto (AI)</h2>
    <p>Unggah foto lalu ketik instruksi, seperti di ChatGPT. Hasil menjadi dasar edit berikutnya. Tidak ada yang disimpan di server.</p>
    <label>Foto<input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" disabled={busy} onChange={(event) => pickFile(event.target.files?.[0] || null)} /></label>
    {inputImage && <div className="image-preview"><img src={inputImage} alt="Foto saat ini" loading="lazy" /></div>}
    <label className="prompt-field">Instruksi edit<textarea value={instruction} maxLength={2000} disabled={busy} placeholder="mis. ganti latar jadi kayu rustic, cahaya hangat, tambah uap mengepul" onChange={(event) => setInstruction(event.target.value)} /></label>
    {error && <p role="alert">{error}</p>}
    <div className="provider-actions">
      <button type="button" disabled={busy || !inputImage || !instruction.trim()} onClick={submit}>{busy ? 'Mengedit…' : 'Kirim edit'}</button>
      {turns.length > 0 && <button type="button" disabled={busy} onClick={reset}>Mulai baru</button>}
    </div>
    {turns.length > 0 && <ol className="edit-turns">{turns.map((turn, index) => <li key={index}>
      <p className="edit-instruction">{turn.instruction}</p>
      <div className="image-preview"><img src={turn.image} alt={`Hasil edit ${index + 1}`} loading="lazy" /></div>
      <a href={turn.image} download={`edit-${index + 1}.png`}>Unduh hasil {index + 1}</a>
    </li>)}</ol>}
  </section>
}
