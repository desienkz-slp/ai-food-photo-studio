"use client"

import { ProviderSettings } from "./provider-settings"
import { Assets } from "./assets"
import { AuthPanel, type WorkspaceItem } from "./auth-panel"
import { useState } from "react"


export function Workspace() {
  const [workspaces, setWorkspaces] = useState<WorkspaceItem[]>([])
  const message = "Unggah belum tersedia karena server belum terhubung."
  return <main className="workspace">
    <AuthPanel onWorkspaces={setWorkspaces} />
    <header><p className="eyebrow">FOOD PHOTO STUDIO</p><h1>Ruang kerja foto makanan</h1><span className="status"><span aria-hidden="true">● </span><span>Belum terhubung</span></span><p>Server dan layanan AI belum terhubung.</p></header>
    <section className="card"><h2>Mulai dari foto makanan</h2><p>Unggah foto setelah koneksi server tersedia. Tidak ada berkas yang dikirim saat ini.</p><p role="status">{message}</p></section>
    <section className="card"><h2>Analisis AI belum tersedia</h2><p>Belum ada hasil untuk ditampilkan karena layanan AI belum terhubung.</p></section>
    <Assets key={`assets-${workspaces.map((item) => item.id).join(',')}`} workspaces={workspaces} />
    <ProviderSettings key={workspaces.map((item) => item.id).join(',')} workspaces={workspaces} />
  </main>
}
