<script>
    /**
     * AppLayout — base shell for every Verdict page.
     *
     * Props:
     *   children  (snippet) – page body rendered inside <main>
     *   title     (string)  – optional HTML <title> suffix
     */
    let { children, title = 'Verdict' } = $props();
</script>

<svelte:head>
    <title>{title}</title>
</svelte:head>

<div class="app-shell">
    <!-- ── Navigation bar ── -->
    <header class="navbar">
        <nav class="navbar__inner">
            <a class="navbar__brand" href="/">
                <span class="navbar__logo" aria-hidden="true">⚖</span>
                <span class="navbar__name">Verdict</span>
            </a>

            <ul class="navbar__links" role="list">
                <li><a href="/">Home</a></li>
                <li><a href="/content_sets">Content Sets</a></li>
            </ul>
        </nav>
    </header>

    <!-- ── Main content ── -->
    <main class="main-content">
        {@render children()}
    </main>

    <!-- ── Footer ── -->
    <footer class="site-footer">
        <p>&copy; {new Date().getFullYear()} Verdict</p>
    </footer>
</div>

<style>
    /* ── Reset / tokens ── */
    :global(*, *::before, *::after) {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    :global(body) {
        font-family: system-ui, -apple-system, sans-serif;
        background: #f8fafc;
        color: #0f172a;
        min-height: 100dvh;
    }

    :global(a) {
        color: inherit;
        text-decoration: none;
    }

    /* ── Shell layout ── */
    .app-shell {
        display: flex;
        flex-direction: column;
        min-height: 100dvh;
    }

    /* ── Navbar ── */
    .navbar {
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .navbar__inner {
        max-width: 72rem;
        margin-inline: auto;
        padding: 0 1.25rem;
        height: 3.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .navbar__brand {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 700;
        font-size: 1.125rem;
        letter-spacing: -0.02em;
    }

    .navbar__logo {
        font-size: 1.25rem;
    }

    .navbar__links {
        display: flex;
        list-style: none;
        gap: 1.5rem;
        font-size: 0.9375rem;
    }

    .navbar__links a:hover {
        color: #6366f1;
    }

    /* ── Main content ── */
    .main-content {
        flex: 1;
        max-width: 72rem;
        width: 100%;
        margin-inline: auto;
        padding: 2rem 1.25rem;
    }

    /* ── Footer ── */
    .site-footer {
        border-top: 1px solid #e2e8f0;
        padding: 1rem 1.25rem;
        text-align: center;
        font-size: 0.8125rem;
        color: #94a3b8;
    }

    /* ── Responsive ── */
    @media (max-width: 640px) {
        .navbar__links {
            display: none; /* collapsed on mobile — expand in a later iteration */
        }

        .main-content {
            padding: 1.25rem 1rem;
        }
    }
</style>
