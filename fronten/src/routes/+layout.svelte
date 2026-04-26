<script>
  import favicon from "$lib/assets/favicon.svg";
  import { page } from "$app/stores";
  import Sidebar from "$lib/components/Sidebar.svelte";
  import Topbar from "$lib/components/Topbar.svelte";
  import MobileNav from "$lib/components/MobileNav.svelte";
  import "../app.css";

  let { children, data } = $props();
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
