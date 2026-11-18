import { useMessages } from '@schemastud/messages/react';
import catalog from '../../../messages.default.json';

/**
 * localization 35: this host's OWN copy, read through the one messages seam. The keys and their English defaults live in
 * the committed root `messages.default.json` (unnamespaced host grammar, `group.item`; ticket 02 "Host input"). With no
 * catalog served the seam renders these English defaults, byte-identical to the literals they replace; with the served
 * catalog (the host's <BeamMessages>, once the page carries one) it renders the served line.
 */
export const hostMessageDefaults = catalog.messages;

export function useHostMessages() {
    return useMessages(hostMessageDefaults);
}
