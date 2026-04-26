<script>
  import { browser } from "$app/environment";
  import { onMount } from "svelte";
  import {
    applyThemePreference,
    getStoredThemePreference,
    resolveTheme,
  } from "$lib/theme";

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
