<script>
  let { form } = $props();
</script>

<div
  class="page-wrap"
  style="max-width: 520px; min-height: calc(100vh - 4rem); display: grid; align-items: center;"
>
  <section
    class="glass-card"
    style="padding: 1.4rem; border-radius: 0.9rem; box-shadow: 0 0 50px rgba(255, 171, 0, 0.15);"
  >
    <span class="eyebrow">Guild Access</span>
    <h1
      class="page-title"
      style="font-size: clamp(2rem, 6vw, 3rem); margin-bottom: 0.7rem;"
    >
      Sign In
    </h1>

    {#if form?.message}
      <p
        style="margin: 0 0 1rem; padding: 0.65rem; border-radius: 0.55rem; border: 1px solid rgba(249, 171, 255, 0.35); color: var(--rare); background: rgba(249, 171, 255, 0.08);"
      >
        {form.message}
      </p>
    {/if}

    <form method="POST" style="display: grid; gap: 0.9rem;">
      <label style="display: grid; gap: 0.35rem;">
        <span
          class="eyebrow"
          style="font-size: 0.62rem; color: var(--text-muted);">Email</span
        >
        <input
          name="email"
          type="email"
          required
          value={form?.email ?? ""}
          class="forge-input"
          style="min-height: auto;"
          autocomplete="email"
          placeholder="hero@taskslayer.dev"
        />
      </label>

      <label style="display: grid; gap: 0.35rem;">
        <span
          class="eyebrow"
          style="font-size: 0.62rem; color: var(--text-muted);">Password</span
        >
        <input
          name="password"
          type="password"
          required
          class="forge-input"
          style="min-height: auto;"
          autocomplete="current-password"
          placeholder="********"
        />
      </label>

      <button
        type="submit"
        class="action-epic"
        style="margin-top: 0.35rem; width: 100%;">Enter the Citadel</button
      >
    </form>
  </section>
</div>
