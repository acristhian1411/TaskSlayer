<script>
  import { browser } from "$app/environment";
  import { onMount } from "svelte";
  import {
    applyThemePreference,
    getStoredThemePreference,
    resolveTheme,
  } from "$lib/theme";

  let { data, form } = $props();

  const themeOptions = [
    {
      value: "system",
      label: "System",
      description: "Follow the device preference automatically.",
    },
    {
      value: "dark",
      label: "Dark",
      description: "Obsidian guild look with golden highlights.",
    },
    {
      value: "light",
      label: "Light",
      description: "Daylight board for longer planning sessions.",
    },
  ];

  let themePreference = $state("system");
  let activeTheme = $state("dark");

  function syncThemeState(preference) {
    themePreference = preference;
    activeTheme = resolveTheme(preference);
  }

  function setThemePreference(preference) {
    const result = applyThemePreference(preference);
    syncThemeState(result.preference);
  }

  onMount(() => {
    if (!browser) return;

    syncThemeState(getStoredThemePreference());

    const mediaQuery = window.matchMedia("(prefers-color-scheme: light)");
    const handleSystemThemeChange = () => {
      if (themePreference === "system") {
        syncThemeState(themePreference);
      }
    };

    mediaQuery.addEventListener("change", handleSystemThemeChange);

    return () => {
      mediaQuery.removeEventListener("change", handleSystemThemeChange);
    };
  });

  const llmConfig = $derived(form?.llmConfig || data?.llmConfig || null);
</script>

<div class="page-wrap settings-page">
  <span class="eyebrow">System</span>
  <h1 class="page-title">Settings</h1>

  <section class="glass-card settings-card">
    <div class="settings-card__header">
      <div>
        <p class="settings-label">Appearance</p>
        <h2 class="settings-title">Theme Mode</h2>
      </div>
      <div class="settings-badge">Active: {activeTheme}</div>
    </div>

    <p class="settings-copy">
      Choose how TaskSlayer should render the board, forge and session screens.
    </p>

    <div class="settings-theme-grid">
      {#each themeOptions as option}
        <button
          type="button"
          class="settings-theme-option"
          class:active={themePreference === option.value}
          onclick={() => setThemePreference(option.value)}
        >
          <span class="settings-theme-option__label">{option.label}</span>
          <span class="settings-theme-option__description"
            >{option.description}</span
          >
        </button>
      {/each}
    </div>
  </section>

  <section class="glass-card settings-card" style="margin-top: 1rem;">
    <div class="settings-card__header">
      <div>
        <p class="settings-label">AI</p>
        <h2 class="settings-title">LLM Connection</h2>
      </div>
      <div class="settings-badge">Default: LM Studio</div>
    </div>

    <p class="settings-copy">
      Configure OpenAI-compatible providers. If you reset this form, TaskSlayer
      uses LM Studio values from <strong>fronten/.env</strong>.
    </p>

    {#if form?.message}
      <p class="settings-status settings-status--ok">{form.message}</p>
    {/if}

    <form method="POST" action="?/saveLlm" class="settings-llm-form">
      <label class="settings-field">
        <span class="settings-label">Provider</span>
        <select name="provider" class="forge-input forge-input--compact">
          <option value="lmstudio" selected={llmConfig?.provider === "lmstudio"}
            >LM Studio</option
          >
          <option
            value="openai-compatible"
            selected={llmConfig?.provider === "openai-compatible"}
            >OpenAI-compatible</option
          >
        </select>
      </label>

      <label class="settings-field">
        <span class="settings-label">Base URL</span>
        <input
          class="forge-input forge-input--compact"
          type="url"
          name="base_url"
          required
          value={llmConfig?.baseUrl || ""}
          placeholder="https://api.openai.com/v1"
        />
      </label>

      <label class="settings-field">
        <span class="settings-label">Model</span>
        <input
          class="forge-input forge-input--compact"
          type="text"
          name="model"
          required
          value={llmConfig?.model || ""}
          placeholder="gpt-4.1-mini"
        />
      </label>

      <label class="settings-field">
        <span class="settings-label">API Key</span>
        <input
          class="forge-input forge-input--compact"
          type="password"
          name="api_key"
          value={llmConfig?.apiKey || ""}
          placeholder="sk-..."
        />
      </label>

      <div class="settings-llm-actions">
        <button type="submit" class="action-epic">Save LLM Settings</button>
      </div>
    </form>

    <form method="POST" action="?/resetLlm" class="settings-reset-form">
      <button type="submit" class="task-link-btn task-link-btn--ghost">
        Reset to LM Studio .env Defaults
      </button>
    </form>
  </section>

  <section class="settings-preview-grid">
    <article class="glass-card settings-preview-card">
      <p class="settings-label">Current Preference</p>
      <h3 class="settings-preview-title">{themePreference}</h3>
      <p class="settings-copy">
        {themePreference === "system"
          ? "The interface tracks the operating system scheme."
          : `The interface stays locked to ${themePreference} mode.`}
      </p>
    </article>

    <article class="glass-card settings-preview-card">
      <p class="settings-label">Preview</p>
      <h3 class="settings-preview-title">Board Atmosphere</h3>
      <p class="settings-copy">
        The palette updates immediately and persists across reloads.
      </p>
      <div class="settings-preview-swatches" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </article>
  </section>
</div>
