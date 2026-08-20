// NEUTRAL theme tokens for the promoted visual-editor canvas (@splicewire/beam-ux/canvas). The package
// canvas ships GENERIC (neutral) defaults; the host supplies its palette here — the "config, not
// machinery" boundary (frontend-surfaces.md, editor). This starter keeps a plain slate/neutral set (NOT a
// brand palette) so a fresh host sees the OOTB shape, then swaps these tokens for its own.
import type { CanvasTheme } from '@splicewire/beam-ux/canvas';

export const NEUTRAL_THEME: Partial<CanvasTheme> = {
    accent: '#0f172a', // slate-900
    accentHover: '#1e293b', // slate-800
    editAccent: '#2563eb', // blue-600 (inline-edit outline)
    canvas: '#ffffff',
    ink: '#0f172a',
    panelBg: '#0f172a',
    rootBg: '#020617',
    panelFg: '#e2e8f0',
    muted: '#64748b',
    fontBody: 'system-ui, sans-serif',
    fontMono: 'ui-monospace, monospace',
};
