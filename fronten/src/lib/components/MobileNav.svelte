<script>
  import { page } from "$app/stores";

  const links = [
    { href: "/", label: "Quests" },
    { href: "/forge", label: "Forge" },
    { href: "/treasury", label: "Treasury" },
    { href: "/stats", label: "Stats" },
  ];
</script>

<nav class="mobile-nav">
  {#each links as link}
    <a href={link.href} class:active={$page.url.pathname === link.href}
      >{link.label}</a
    >
  {/each}
</nav>
