<script>
  import { browser } from "$app/environment";
  import favicon from "$lib/assets/favicon.svg";
  import { page } from "$app/stores";
  import { onMount } from "svelte";
  import Sidebar from "$lib/components/Sidebar.svelte";
  import Topbar from "$lib/components/Topbar.svelte";
  import MobileNav from "$lib/components/MobileNav.svelte";
  import {
    getStoredThemePreference,
    syncStoredThemePreference,
  } from "$lib/theme";
  import "../app.css";

  let { children, data } = $props();

  onMount(() => {
    if (!browser) return;

    syncStoredThemePreference();

    const mediaQuery = window.matchMedia("(prefers-color-scheme: light)");
    const handleSystemThemeChange = () => {
      if (getStoredThemePreference() === "system") {
        syncStoredThemePreference();
      }
    };

    const handleStorage = (event) => {
      if (event.key === null || event.key === "taskslayer.theme") {
        syncStoredThemePreference();
      }
    };

    mediaQuery.addEventListener("change", handleSystemThemeChange);
    window.addEventListener("storage", handleStorage);

    return () => {
      mediaQuery.removeEventListener("change", handleSystemThemeChange);
      window.removeEventListener("storage", handleStorage);
    };
  });
</script>

<svelte:head>
  <link rel="icon" href={favicon} />
  <title>TaskSlayer</title>
</svelte:head>

<div class="grain"></div>

{#if $page.url.pathname.startsWith("/session") || $page.url.pathname.startsWith("/login")}
  {@render children()}
{:else}
  <div class="shell">
    <Sidebar user={data.user} />

    <main class="main">
      <Topbar user={data.user} />
      {@render children()}
    </main>
  </div>

  <MobileNav />
{/if}
