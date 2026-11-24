/**
 * Stamped by `splicewire:beam:ux:stamp-shell`: registers this host's generated resource map with frame, so a key frame
 * does not know stops compiling and each shell's rows are typed by its key. `splicewire:beam:generate:client` writes the
 * map. It is the host's now: widen a key here when its rows vary in a way no declaration names. A re-stamp keeps a
 * tailored copy unless --force.
 */
import type {} from '@schemastud/frame';
import type { FrameResources } from '@/generated/frame-resources';

declare module '@schemastud/frame' {
    // Module augmentation: an empty interface that extends is the only form that merges
    // into frame's FrameResourceMap (a type alias does not merge inside `declare module`).
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface FrameResourceMap extends FrameResources {}
}
