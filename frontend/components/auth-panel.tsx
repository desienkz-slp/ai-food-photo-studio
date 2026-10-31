"use client"

import { useEffect, useState } from "react"

export type WorkspaceItem = { id: number; name: string }
const options: RequestInit = { credentials: 'include', headers: { Accept: 'application/json' }, redirect: 'error', cache: 'no-store' }

function csrfToken() {
  const value = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='))?.slice(11)
  if (!value) return null
  try { return decodeURIComponent(value) || null } catch { return null }
}

export function AuthPanel({ onWorkspaces }: { onWorkspaces?: (items: WorkspaceItem[]) => void }) {
  const [active, setActive] = useState(false)
  const [loading, setLoading] = useState(true)
  const [email, setEmail] = useState(''); const [password, setPassword] = useState('')
  const [error, setError] = useState(''); const [busy, setBusy] = useState(false)

  useEffect(() => {
    const controller = new AbortController()
    async function bootstrap() {
      try {
        const csrf = await fetch('/csrf-cookie', { ...options, signal: controller.signal })
        if (!csrf.ok) throw new Error()
        const session = await fetch('/session', { ...options, signal: controller.signal })
        if (session.status === 401) return
        if (!session.ok) throw new Error()
        const result = await session.json()
        if (!result.user || typeof result.user.id !== 'number') throw new Error()
        if (!controller.signal.aborted) setActive(true)
      } catch { if (!controller.signal.aborted) setError('Sesi atau koneksi tidak tersedia. Muat ulang halaman.') }
      finally { if (!controller.signal.aborted) setLoading(false) }
    }
    void bootstrap()
    return () => controller.abort()
  }, [])

  useEffect(() => {
    if (!active || !onWorkspaces) return
    const controller = new AbortController()
    async function loadWorkspaces() {
      try {
        const response = await fetch('/workspaces', { ...options, signal: controller.signal })
        if (!response.ok) throw new Error()
        const result = await response.json()
        if (!Array.isArray(result.data) || !result.data.every((item: WorkspaceItem) => Number.isSafeInteger(item.id) && item.id > 0 && typeof item.name === 'string')) throw new Error()
        if (!controller.signal.aborted) onWorkspaces!(result.data)
      } catch { if (!controller.signal.aborted) { onWorkspaces!([]); setError('Workspace tidak dapat dimuat. Muat ulang halaman.') } }
    }
    void loadWorkspaces()
    return () => controller.abort()
  }, [active, onWorkspaces])

  async function submit(event: React.FormEvent) {
    event.preventDefault(); const token = csrfToken()
    if (!token) { setError('Cookie CSRF tidak tersedia.'); return }
    setBusy(true); setError('')
    try { const response = await fetch('/login', { ...options, method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token }, body: JSON.stringify({ email, password }) }); if (!response.ok) throw new Error(); setActive(true); setPassword('') } catch { setError('Login gagal. Periksa kredensial dan sesi.') } finally { setBusy(false) }
  }
  async function logout() {
    const token = csrfToken(); if (!token) { setError('Cookie CSRF tidak tersedia.'); return }
    setBusy(true); setError(''); try { const response = await fetch('/logout', { ...options, method: 'POST', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token } }); if (!response.ok) throw new Error(); setActive(false); onWorkspaces?.([]) } catch { setError('Logout gagal.'); } finally { setBusy(false) }
  }
  if (loading) return <section className="card" aria-label="Status sesi"><p>Memeriksa sesi…</p></section>
  if (active) return <section className="card" aria-label="Status sesi"><p>Sesi aktif</p><button type="button" onClick={logout} disabled={busy}>Logout</button>{error && <p role="alert">{error}</p>}</section>
  return <section className="card" aria-label="Status sesi"><h2>Sesi belum terhubung</h2><form onSubmit={submit} autoComplete="off"><label>Email<input type="email" value={email} onChange={(event) => setEmail(event.target.value)} /></label><label>Password<input type="password" value={password} onChange={(event) => setPassword(event.target.value)} /></label><button type="submit" disabled={busy || !email || !password}>Login</button></form>{error && <p role="alert">{error}</p>}</section>
}
