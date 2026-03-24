<script lang="ts">
    /**
     * Sessions/Create — form to start a new rating session.
     *
     * Props (injected by SessionController@create via Inertia):
     *   contentSets   – available ContentSet[] the host can pick from
     *   ratingConfigs – available RatingConfig[] for the session
     *   auth          – authenticated User object, or null for guests
     *
     * Validation errors are surfaced inline via Inertia's useForm helper,
     * which replays server-side errors directly onto form.errors.
     */
    import AppLayout from '@/Layouts/AppLayout.svelte';
    import { useForm } from '@inertiajs/svelte';
    import type { ContentSet } from '@/types/content';
    import type { RatingConfig } from '@/types/session';

    // ── Auth user shape (subset of App\Models\User) ──────────────────────────
    interface AuthUser {
        id: number;
        name: string;
        email: string;
    }

    interface Props {
        contentSets: ContentSet[];
        ratingConfigs: RatingConfig[];
        auth: AuthUser | null;
    }

    let { contentSets, ratingConfigs, auth }: Props = $props();

    // ── Form state ────────────────────────────────────────────────────────────
    const form = useForm({
        content_set_id:   null as number | null,
        rating_config_id: null as number | null,
        display_name:     '',
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        $form.post('/sessions');
    }

    /** Human-readable hint for a RatingConfig — shown in the option label. */
    function ratingHint(rc: RatingConfig): string {
        if (rc.type === 'tier') {
            const labels = rc.rating_tiers?.map(t => t.label).join(' / ') ?? 'Tiers';
            return `Tier: ${labels}`;
        }
        return 'Numeric scale';
    }
</script>

<AppLayout title="Create Session — Verdict">
    <div class="create">

        <!-- ── Page header ─────────────────────────────────────────────────── -->
        <header class="create__header">
            <nav class="create__breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true"> / </span>
                <span aria-current="page">Create Session</span>
            </nav>

            <h1 class="create__title">Create a Rating Session</h1>
            <p class="create__subtitle">
                Pick a content set, choose a rating style, and share the code with friends.
            </p>
        </header>

        <!-- ── Form ───────────────────────────────────────────────────────── -->
        <form class="create__form" onsubmit={submit} novalidate>

            <!-- Content Set selector -->
            <div class="field">
                <label class="field__label" for="content_set_id">
                    Content Set
                    <span class="field__required" aria-hidden="true">*</span>
                </label>

                <select
                    id="content_set_id"
                    class="field__select"
                    class:field__select--error={!!$form.errors.content_set_id}
                    aria-describedby={$form.errors.content_set_id ? 'err-content_set_id' : undefined}
                    bind:value={$form.content_set_id}
                >
                    <option value={null}>— Select a content set —</option>
                    {#each contentSets as cs (cs.id)}
                        <option value={cs.id}>
                            {cs.name}{cs.items_count !== null ? ` (${cs.items_count} items)` : ''}
                        </option>
                    {/each}
                </select>

                {#if $form.errors.content_set_id}
                    <p id="err-content_set_id" class="field__error" role="alert">
                        {$form.errors.content_set_id}
                    </p>
                {/if}
            </div>

            <!-- Rating Config selector -->
            <div class="field">
                <label class="field__label" for="rating_config_id">
                    Rating Style
                    <span class="field__required" aria-hidden="true">*</span>
                </label>

                <select
                    id="rating_config_id"
                    class="field__select"
                    class:field__select--error={!!$form.errors.rating_config_id}
                    aria-describedby={$form.errors.rating_config_id ? 'err-rating_config_id' : undefined}
                    bind:value={$form.rating_config_id}
                >
                    <option value={null}>— Select a rating style —</option>
                    {#each ratingConfigs as rc (rc.id)}
                        <option value={rc.id}>{rc.name} — {ratingHint(rc)}</option>
                    {/each}
                </select>

                {#if $form.errors.rating_config_id}
                    <p id="err-rating_config_id" class="field__error" role="alert">
                        {$form.errors.rating_config_id}
                    </p>
                {/if}
            </div>

            <!-- Display name — guests only (auth === null) -->
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
                        class:field__input--error={!!$form.errors.display_name}
                        aria-describedby={$form.errors.display_name ? 'err-display_name' : undefined}
                        placeholder="e.g. Alice"
                        maxlength="64"
                        autocomplete="nickname"
                        bind:value={$form.display_name}
                    />

                    {#if $form.errors.display_name}
                        <p id="err-display_name" class="field__error" role="alert">
                            {$form.errors.display_name}
                        </p>
                    {/if}
                </div>
            {/if}

            <!-- Submit -->
            <div class="create__actions">
                <button
                    type="submit"
                    class="btn btn--primary"
                    disabled={$form.processing}
                    aria-busy={$form.processing}
                >
                    {$form.processing ? 'Creating…' : 'Create Session'}
                </button>

                <a href="/" class="btn btn--ghost">Cancel</a>
            </div>

        </form>

    </div>
</AppLayout>

<style>
    /* ── Page shell ── */
    .create {
        max-width: 36rem;
        margin-inline: auto;
    }

    /* ── Header ── */
    .create__header {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        margin-bottom: 2rem;
    }

    .create__breadcrumb {
        font-size: 0.8125rem;
        color: #94a3b8;
    }

    .create__breadcrumb a {
        color: #6366f1;
        text-decoration: none;
    }

    .create__breadcrumb a:hover {
        text-decoration: underline;
    }

    .create__title {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: #0f172a;
        margin-top: 0.375rem;
    }

    .create__subtitle {
        font-size: 0.9375rem;
        color: #64748b;
        line-height: 1.6;
    }

    /* ── Form ── */
    .create__form {
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

    .field__select,
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
        appearance: auto;
    }

    .field__select:focus,
    .field__input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    .field__select--error,
    .field__input--error {
        border-color: #ef4444;
    }

    .field__select--error:focus,
    .field__input--error:focus {
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
    }

    .field__error {
        font-size: 0.8125rem;
        color: #ef4444;
        margin-top: 0.125rem;
    }

    /* ── Actions ── */
    .create__actions {
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
        .create__actions {
            flex-direction: column;
            align-items: stretch;
        }

        .btn {
            width: 100%;
        }
    }
</style>
