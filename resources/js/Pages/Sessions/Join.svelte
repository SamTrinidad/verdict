<script lang="ts">
    /**
     * Sessions/Join — entry point for participants who have a session code.
     *
     * Props (injected by SessionController@joinForm via Inertia):
     *   auth – authenticated User object, or null for guests
     *
     * The user types the 26-character ULID join code (auto-uppercased).
     * Authenticated users skip the display_name field — the server uses
     * their account name instead.
     *
     * Submits:  POST /sessions/{ulid}/enter
     * On error: validation messages are shown inline (server-returned errors
     *           are surfaced via the Inertia onError callback).
     */
    import AppLayout from '@/Layouts/AppLayout.svelte';
    import { router } from '@inertiajs/svelte';

    // ── Auth user shape ───────────────────────────────────────────────────────
    interface AuthUser {
        id: number;
        name: string;
        email: string;
    }

    interface Props {
        auth: AuthUser | null;
    }

    let { auth }: Props = $props();

    // ── Form state ────────────────────────────────────────────────────────────
    let ulid        = $state('');
    let displayName = $state('');
    let errors      = $state<Record<string, string>>({});
    let processing  = $state(false);

    /** A ULID is exactly 26 Crockford Base-32 characters. */
    const ULID_LENGTH = 26;

    let ulidValid = $derived(ulid.length === ULID_LENGTH);

    // ── Handlers ─────────────────────────────────────────────────────────────

    /** Force uppercase as the user types; ULIDs are always upper-case. */
    function handleUlidInput(e: Event) {
        const raw = (e.currentTarget as HTMLInputElement).value;
        ulid = raw.toUpperCase().replace(/[^0123456789ABCDEFGHJKMNPQRSTVWXYZ]/g, '');
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();

        if (!ulidValid) {
            errors = { ulid: `Session code must be exactly ${ULID_LENGTH} characters.` };
            return;
        }

        processing = true;
        errors = {};

        router.post(
            `/sessions/${ulid}/enter`,
            { display_name: displayName },
            {
                onError:  (errs) => { errors = errs; },
                onFinish: ()     => { processing = false; },
            },
        );
    }
</script>

<AppLayout title="Join Session — Verdict">
    <div class="join">

        <!-- ── Page header ─────────────────────────────────────────────────── -->
        <header class="join__header">
            <nav class="join__breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true"> / </span>
                <span aria-current="page">Join Session</span>
            </nav>

            <h1 class="join__title">Join a Session</h1>
            <p class="join__subtitle">
                Enter the 26-character session code shared by the host.
            </p>
        </header>

        <!-- ── Form ───────────────────────────────────────────────────────── -->
        <form class="join__form" onsubmit={submit} novalidate>

            <!-- ULID code input -->
            <div class="field">
                <label class="field__label" for="ulid">
                    Session Code
                    <span class="field__required" aria-hidden="true">*</span>
                </label>

                <div class="field__code-wrap">
                    <input
                        id="ulid"
                        type="text"
                        class="field__input field__input--code"
                        class:field__input--error={!!errors.ulid}
                        aria-describedby={errors.ulid ? 'err-ulid' : 'hint-ulid'}
                        placeholder="01ABC2DEF3GHJ4KMN5PQR6STVW"
                        maxlength={ULID_LENGTH}
                        spellcheck="false"
                        autocomplete="off"
                        autocapitalize="characters"
                        value={ulid}
                        oninput={handleUlidInput}
                    />
                    <!-- Character counter -->
                    <span
                        class="field__counter"
                        class:field__counter--ready={ulidValid}
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        {ulid.length}/{ULID_LENGTH}
                    </span>
                </div>

                {#if errors.ulid}
                    <p id="err-ulid" class="field__error" role="alert">{errors.ulid}</p>
                {:else}
                    <p id="hint-ulid" class="field__hint">
                        Codes are case-insensitive — paste or type the code from the host.
                    </p>
                {/if}
            </div>

            <!-- Display name — guests only -->
            {#if !auth}
                <div class="field">
                    <label class="field__label" for="display_name">
                        Your Display Name
                        <span class="field__required" aria-hidden="true">*</span>
                    </label>
                    <p class="field__hint">
                        Visible to other participants in the session.
                    </p>

                    <input
                        id="display_name"
                        type="text"
                        class="field__input"
                        class:field__input--error={!!errors.display_name}
                        aria-describedby={errors.display_name ? 'err-display_name' : undefined}
                        placeholder="e.g. Bob"
                        maxlength="64"
                        autocomplete="nickname"
                        bind:value={displayName}
                    />

                    {#if errors.display_name}
                        <p id="err-display_name" class="field__error" role="alert">
                            {errors.display_name}
                        </p>
                    {/if}
                </div>
            {/if}

            <!-- Submit -->
            <div class="join__actions">
                <button
                    type="submit"
                    class="btn btn--primary"
                    disabled={processing}
                    aria-busy={processing}
                >
                    {processing ? 'Joining…' : 'Join Session'}
                </button>

                <a href="/" class="btn btn--ghost">Cancel</a>
            </div>

        </form>

    </div>
</AppLayout>

<style>
    /* ── Page shell ── */
    .join {
        max-width: 32rem;
        margin-inline: auto;
    }

    /* ── Header ── */
    .join__header {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        margin-bottom: 2rem;
    }

    .join__breadcrumb {
        font-size: 0.8125rem;
        color: #94a3b8;
    }

    .join__breadcrumb a {
        color: #6366f1;
        text-decoration: none;
    }

    .join__breadcrumb a:hover {
        text-decoration: underline;
    }

    .join__title {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: #0f172a;
        margin-top: 0.375rem;
    }

    .join__subtitle {
        font-size: 0.9375rem;
        color: #64748b;
        line-height: 1.6;
    }

    /* ── Form ── */
    .join__form {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* ── Field ── */
    .field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .field__label {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #1e293b;
    }

    .field__required {
        color: #ef4444;
        margin-left: 0.125rem;
    }

    .field__hint {
        font-size: 0.8125rem;
        color: #94a3b8;
        margin-top: -0.125rem;
    }

    .field__code-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .field__input {
        width: 100%;
        padding: 0.5625rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        font-size: 0.9375rem;
        font-family: inherit;
        background: #ffffff;
        color: #0f172a;
        outline: none;
        transition: border-color 0.12s, box-shadow 0.12s;
    }

    .field__input--code {
        /* Monospace for ULID readability */
        font-family: ui-monospace, 'Cascadia Code', 'Fira Code', monospace;
        font-size: 1rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        padding-right: 4rem; /* room for counter */
    }

    .field__input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    .field__input--error {
        border-color: #ef4444;
    }

    .field__input--error:focus {
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
    }

    .field__counter {
        position: absolute;
        right: 0.75rem;
        font-size: 0.75rem;
        font-variant-numeric: tabular-nums;
        color: #94a3b8;
        pointer-events: none;
        user-select: none;
        transition: color 0.12s;
    }

    .field__counter--ready {
        color: #22c55e;
        font-weight: 600;
    }

    .field__error {
        font-size: 0.8125rem;
        color: #ef4444;
        margin-top: 0.125rem;
    }

    /* ── Actions ── */
    .join__actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        margin-top: 0.5rem;
    }

    /* ── Buttons ── */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.625rem 1.375rem;
        border-radius: 0.5rem;
        font-size: 0.9375rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        border: 2px solid transparent;
        text-decoration: none;
        transition: background 0.12s, opacity 0.12s, border-color 0.12s;
    }

    .btn--primary {
        background: #6366f1;
        color: #ffffff;
        border: none;
    }

    .btn--primary:hover:not(:disabled) {
        background: #4f46e5;
    }

    .btn--primary:disabled {
        opacity: 0.65;
        cursor: not-allowed;
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

    /* ── Responsive ── */
    @media (max-width: 480px) {
        .join__actions {
            flex-direction: column;
            align-items: stretch;
        }

        .btn {
            width: 100%;
        }
    }
</style>
