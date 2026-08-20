import { Head, Link, usePage } from '@inertiajs/react';
import { SiteLayout as BeamSiteLayout } from '@splicewire/beam-ux/site';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import SiteNav from '@/components/site-nav';
import { dashboard, logout } from '@/routes';

/**
 * The public-site chrome (header + footer) for the starter, in a NEUTRAL theme. The STRUCTURE comes
 * from the generic package `<SiteLayout>` (`@splicewire/beam-ux/site`); this wrapper supplies only the
 * host THEME + the brand + the nav/footer slots. This is the "wrote only config" proof for the site
 * realm — no site-chrome machinery lives in the host.
 *
 * Nav CONTENT is data-driven from the shared `nav` prop via <SiteNav>; the auth affordance + brand are
 * hand-placed here. Every public page wraps its body in <SiteLayout>.
 *
 * theme-entries-and-authoring ticket `str-01`: `.navlink`/`.btn-primary` read `--theme-site-*` custom
 * properties instead of hardcoded hex — {@see ThemeSiteStyle} declares those vars from
 * `page.props.theme.site` (the `ThemeResolver` cascade). `#fff` (button text on the accent
 * background) stays a literal — it has no corresponding named token in `theme.site`'s 6-field shape.
 *
 * Every `var(--theme-site-*, <today's value>)` carries today's hardcoded hex as its CSS fallback —
 * unlike the shell side (which has `OS_SHELL_CSS`'s own literal values as an always-present base
 * layer underneath `ThemeShellStyle`'s override), site has only ONE style layer. Without a fallback, a
 * missing `page.props.theme.site` would compute `background`/`color` to their CSS initial value
 * (transparent — `background-color` isn't inherited), not today's color — an invisible button, not a
 * safe degrade.
 */
const CSS = `
.st-site a{color:inherit;text-decoration:none}
.st-site .navlink{font-size:14px;color:var(--theme-site-muted, #475569);text-decoration:none;transition:color .15s}
.st-site .navlink:hover{color:var(--theme-site-accent, #0f172a)}
.st-site .btn-primary{display:inline-flex;align-items:center;gap:8px;background:var(--theme-site-accent, #0f172a);color:#fff;border:none;border-radius:10px;padding:9px 16px;font:600 14px system-ui;cursor:pointer;transition:background .15s;text-decoration:none}
.st-site .btn-primary:hover{background:var(--theme-site-accent-hover, #1e293b)}
`;

interface SiteThemeTokens {
    background: string;
    foreground: string;
    muted: string;
    accent: string;
    accentHover: string;
    border: string;
}

/** Renders nothing when `page.props.theme.site` is absent — `CSS`'s `var(--theme-site-*, <hex>)` refs
 * fall through to their literal fallback then, matching today's hardcoded palette exactly. */
function ThemeSiteStyle() {
    const page = usePage<{ theme?: { site?: SiteThemeTokens } }>();
    const site = page.props.theme?.site;

    if (!site) {
        return null;
    }

    const css = `.st-site{
  --theme-site-background:${site.background};
  --theme-site-foreground:${site.foreground};
  --theme-site-muted:${site.muted};
  --theme-site-accent:${site.accent};
  --theme-site-accent-hover:${site.accentHover};
  --theme-site-border:${site.border};
}`;

    return <style dangerouslySetInnerHTML={{ __html: css }} />;
}

const brand = (
    <Link href="/" style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
        <AppLogoIcon style={{ width: 22, height: 22, display: 'block', color: '#0f172a' }} />
        <span style={{ fontSize: 15, fontWeight: 600, letterSpacing: '-0.01em', color: '#0f172a' }}>Beam Starter</span>
    </Link>
);

// This site's ONE chrome renders for guests AND signed-in principals alike (SiteLayout has no separate
// authed variant) - the guest "Sign in"/"Dashboard" pair below must reflect real auth state, not a fixed
// guest assumption. `AuthNavLinks` is the one piece of the header that reads `usePage()`.
function AuthNavLinks() {
    const page = usePage<{ auth: { user: { name: string } | null }; can?: Record<string, boolean> }>();
    const { auth, can } = page.props;

    if (!auth.user) {
        return (
            <>
                <a className="navlink" href="/login">
                    Sign in
                </a>
                <a className="btn-primary" href="/dashboard">
                    Dashboard
                </a>
            </>
        );
    }

    return (
        <>
            {can?.['app:operator'] && (
                <a className="navlink" href="/operator">
                    Operator
                </a>
            )}
            <Link className="navlink" href={dashboard()}>
                Dashboard
            </Link>
            <Link className="navlink" href={logout()} as="button">
                Log out
            </Link>
        </>
    );
}

const nav = (
    <div style={{ display: 'flex', alignItems: 'center', gap: 22, marginLeft: 'auto', flexWrap: 'wrap' }}>
        <SiteNav />
        <AuthNavLinks />
    </div>
);

export default function SiteLayout({ children }: { children: ReactNode }) {
    const page = usePage<{ auth: { user: unknown } }>();
    const footerLinks = [
        { title: 'Home', href: '/' },
        { title: 'About', href: '/about' },
        page.props.auth.user ? { title: 'Dashboard', href: '/dashboard' } : { title: 'Sign in', href: '/login' },
    ];

    return (
        <BeamSiteLayout
            linkComponent={Link}
            brand={brand}
            nav={nav}
            footerLinks={footerLinks}
            head={
                <>
                    <Head title="Beam Starter" />
                    <style dangerouslySetInnerHTML={{ __html: CSS }} />
                    <ThemeSiteStyle />
                </>
            }
            footerBrand={brand}
            footerStyle={{
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 16,
                marginTop: 48,
                padding: '28px clamp(18px,5vw,56px)',
                borderTop: '1px solid rgba(15,23,42,.08)',
                color: '#64748b',
                fontSize: 13,
            }}
            footerLinkClassName="navlink"
            footerLinkStyle={{ marginLeft: 18 }}
            className="st-site"
            style={{
                background: '#f8fafc',
                color: '#0f172a',
                fontFamily: 'system-ui, sans-serif',
                WebkitFontSmoothing: 'antialiased',
                minHeight: '100vh',
                display: 'flex',
                flexDirection: 'column',
            }}
            headerStyle={{
                position: 'sticky',
                top: 0,
                zIndex: 40,
                display: 'flex',
                alignItems: 'center',
                gap: 18,
                flexWrap: 'wrap',
                padding: '16px clamp(18px,5vw,56px)',
                background: 'rgba(248,250,252,.86)',
                backdropFilter: 'blur(10px)',
                borderBottom: '1px solid rgba(15,23,42,.08)',
            }}
            mainStyle={{ flex: 1, width: '100%' }}
        >
            {children}
        </BeamSiteLayout>
    );
}
