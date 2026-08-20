// Component registry for the visual editor's OPAQUE ISLANDS. A tree node whose `name` is a registered key
// (PascalCase) renders the REAL designed component (sealed block: select / reorder / delete, no drill-in)
// instead of an intrinsic element. Host CONFIG only — the "is this an island" test lives in the package
// canvas (`isIsland(config, name)`); this file just supplies the map. A fresh host registers its own
// designed sections here.
import type { ComponentType } from 'react';
import ConfirmPassword from '@/pages/auth/confirm-password';
import ForgotPassword from '@/pages/auth/forgot-password';
import Login from '@/pages/auth/login';
import Register from '@/pages/auth/register';
import ResetPassword from '@/pages/auth/reset-password';
import TwoFactorChallenge from '@/pages/auth/two-factor-challenge';
import VerifyEmail from '@/pages/auth/verify-email';
import { DemoFeatureRow, DemoHero } from './islands';

export const COMPONENTS: Record<
    string,
    ComponentType<Record<string, unknown>>
> = {
    DemoHero,
    DemoFeatureRow,
    // theme-entries-and-authoring STR-03: the 7 promoted auth pages' real Fortify-bound forms, sealed
    // (position/delete-only in the visual editor, never decomposed) — the files themselves are
    // UNCHANGED in location/content (chisel's scaffold-time feature-toggle operates on these exact
    // paths; moving them would require updating the starter's own chisel-paths.php).
    AuthLogin: Login,
    AuthRegister: Register,
    AuthForgotPassword: ForgotPassword,
    AuthResetPassword: ResetPassword,
    AuthConfirmPassword: ConfirmPassword,
    AuthTwoFactorChallenge: TwoFactorChallenge,
    AuthVerifyEmail: VerifyEmail,
};
