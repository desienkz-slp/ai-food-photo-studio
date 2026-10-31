"use client"

import { ProviderSettings } from "./provider-settings"
import { PhotoEditor } from "./photo-editor"
import { AuthPanel, type WorkspaceItem } from "./auth-panel"
import { useState } from "react"


export function Workspace() {
  const [, setWorkspaces] = useState<WorkspaceItem[]>([])
  const [isSuperAdmin, setIsSuperAdmin] = useState(false)
  return <AuthPanel onWorkspaces={setWorkspaces} onSuperAdmin={setIsSuperAdmin}>
    <main className="workspace">
      <header><p className="eyebrow">FOOD PHOTO STUDIO</p><h1>Editor foto makanan AI</h1></header>
      <PhotoEditor />
      {isSuperAdmin && <ProviderSettings />}
    </main>
  </AuthPanel>
}
