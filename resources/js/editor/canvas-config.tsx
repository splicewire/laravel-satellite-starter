// The host's CanvasConfig for @splicewire/beam-ux/canvas — the single place that injects app specifics
// into the promoted visual editor: the island registry, the MDX view/edit components, and the host's
// insert palette + class/var suggestions. Machinery is all in the package; this is the "config, not
// machinery" surface a fresh beam host writes (frontend-surfaces.md, editor).
import type { JsonBlock } from '@splicewire/beam-ux/blockdoc/json';
import type { BlockTemplate, CanvasConfig } from '@splicewire/beam-ux/canvas';
import { MdxEdit, MdxView } from './mdx';
import { COMPONENTS } from './registry';

const str = (name: string, value: string) => ({ name, kind: 'string' as const, value });
const el = (name: string, props: { name: string; kind: 'string'; value: string }[], children: JsonBlock['children'] = []): JsonBlock => ({
    kind: 'block',
    name,
    isComponent: /^[A-Z]/.test(name),
    props,
    children,
    dynamic: false,
});

// The insert palette — host content, authored as JsonBlocks (NEUTRAL styling).
const BLOCK_TEMPLATES: BlockTemplate[] = [
    { label: 'Section', make: () => el('section', [str('style', 'padding:40px 0')], [el('p', [], [{ kind: 'text', value: 'New section' }])]) },
    { label: 'Heading', make: () => el('h2', [str('style', 'font-size:28px;font-weight:700;color:#0f172a')], [{ kind: 'text', value: 'New heading' }]) },
    { label: 'Text', make: () => el('p', [str('style', 'color:#475569;line-height:1.6')], [{ kind: 'text', value: 'New paragraph.' }]) },
    { label: 'Button', make: () => el('button', [str('style', 'background:#0f172a;color:#fff;border:none;border-radius:10px;padding:11px 18px;cursor:pointer')], [{ kind: 'text', value: 'Button' }]) },
    { label: 'Row', make: () => el('div', [str('style', 'display:flex;gap:12px')]) },
    { label: 'Divider', make: () => el('hr', [str('style', 'border:none;border-top:1px solid #e2e8f0;margin:24px 0')]) },
];

const CLASS_SUGGESTIONS = ['flex', 'grid', 'items-center', 'justify-between', 'gap-4', 'p-4', 'p-8', 'mt-4', 'mb-6', 'text-center', 'text-lg', 'text-2xl', 'font-semibold', 'rounded-lg', 'rounded-xl', 'shadow', 'w-full', 'max-w-2xl'];
const VAR_SUGGESTIONS = ['var(--shell-accent)', 'var(--shell-fg)', 'var(--shell-surface)'];

export const canvasConfig: CanvasConfig = {
    registry: COMPONENTS,
    MdxView,
    MdxEdit,
    blockTemplates: BLOCK_TEMPLATES,
    classSuggestions: CLASS_SUGGESTIONS,
    varSuggestions: VAR_SUGGESTIONS,
};
