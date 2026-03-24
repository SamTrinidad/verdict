<script>
    /**
     * Welcome page — Inertia renders this as the root "/" route.
     *
     * Inertia passes page props via $props(); add typed fields as needed.
     * Example prop from the controller:  ['appName' => config('app.name')]
     */
    import AppLayout from '../Layouts/AppLayout.svelte';

    let { appName = 'Verdict' } = $props();

    // $state example — will grow once real features are wired
    let count = $state(0);

    // $effect example — logs whenever count changes
    $effect(() => {
        if (count > 0) {
            console.debug(`[Welcome] interaction count: ${count}`);
        }
    });
</script>

<AppLayout title="Welcome — {appName}">
    <div class="welcome">
        <h1 class="welcome__heading">Verdict</h1>
        <p class="welcome__tagline">
            Collaborative real-time rating — rate together, decide faster.
        </p>

        <div class="welcome__actions">
            <a class="btn btn--primary" href="/sessions/create">Start a session</a>
            <a class="btn btn--secondary" href="/sessions/join">Join a session</a>
        </div>

        <!-- Svelte 5 reactivity smoke-test (remove once real features land) -->
        <div class="welcome__counter" aria-live="polite">
            <button class="btn btn--ghost" onclick={() => count++}>
                Clicked {count} {count === 1 ? 'time' : 'times'}
            </button>
        </div>
    </div>
</AppLayout>

<style>
    .welcome {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        gap: 1.5rem;
        padding: 4rem 0;
    }

    .welcome__heading {
        font-size: clamp(2.5rem, 8vw, 5rem);
        font-weight: 800;
        letter-spacing: -0.04em;
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .welcome__tagline {
        font-size: 1.125rem;
        color: #475569;
        max-width: 36rem;
        line-height: 1.6;
    }

    .welcome__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        justify-content: center;
    }

    .welcome__counter {
        margin-top: 1rem;
    }

    /* ── Buttons ── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.625rem 1.375rem;
        border-radius: 0.5rem;
        font-size: 0.9375rem;
        font-weight: 600;
        cursor: pointer;
        border: 2px solid transparent;
        transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
    }

    .btn--primary {
        background: #6366f1;
        color: #ffffff;
    }

    .btn--primary:hover {
        background: #4f46e5;
    }

    .btn--secondary {
        background: transparent;
        border-color: #6366f1;
        color: #6366f1;
    }

    .btn--secondary:hover {
        background: #eef2ff;
    }

    .btn--ghost {
        background: #f1f5f9;
        color: #475569;
        border-color: #e2e8f0;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .btn--ghost:hover {
        background: #e2e8f0;
    }
</style>
