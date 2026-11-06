import { authFeaturesFrom } from '@splicewire/beam-inertia';

export const authFeatures = authFeaturesFrom([
    /* @chisel-registration */
    'registration',
    /* @end-chisel-registration */
    /* @chisel-email-verification */
    'email-verification',
    /* @end-chisel-email-verification */
    /* @chisel-2fa */
    '2fa',
    /* @end-chisel-2fa */
    /* @chisel-passkeys */
    'passkeys',
    /* @end-chisel-passkeys */
    /* @chisel-password-confirmation */
    'password-confirmation',
    /* @end-chisel-password-confirmation */
]);
