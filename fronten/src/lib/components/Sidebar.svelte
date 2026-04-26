<script>
  import { page } from "$app/stores";

  let { user = null } = $props();

  const links = [
    { href: "/", label: "Quests", icon: "⚔" },
    { href: "/forge", label: "Forge", icon: "🛠" },
    { href: "/treasury", label: "Treasury", icon: "◍" },
    { href: "/stats", label: "Stats", icon: "◌" },
  ];
</script>

<aside class="sidebar">
  <div class="brand">TaskSlayer</div>

  <div class="profile">
    <div class="avatar">{(user?.name?.[0] ?? "J").toUpperCase()}</div>
    <div>
      <div
        style="font-family: 'Epilogue', sans-serif; font-size: 0.83rem; font-weight: 700;"
      >
        {user?.name ?? "Hero"}
      </div>
      <div
        style="font-family: 'Space Grotesk', sans-serif; color: var(--gold-soft); font-size: 0.65rem; letter-spacing: 0.1em; text-transform: uppercase;"
      >
        {user?.email ?? "Guild Access"}
      </div>
    </div>
  </div>

  <nav class="nav">
    {#each links as link}
      <a
        class="nav-link"
        class:active={$page.url.pathname === link.href}
        href={link.href}
      >
        <span>{link.label}</span>
        <span aria-hidden="true">{link.icon}</span>
      </a>
    {/each}
  </nav>

  <div style="margin-top: auto; display: grid; gap: 0.45rem;">
    <a class="nav-link" href="/settings">Settings</a>
    <a class="nav-link" href="/logout">Log out</a>
  </div>
</aside>
