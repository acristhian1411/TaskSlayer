<script>
  let { user = null } = $props();
</script>

<header class="topbar">
  <button class="icon-btn" aria-label="Notifications">◐</button>
  <a class="icon-btn icon-btn-link" href="/settings" aria-label="Settings">⚙</a
  >
  <div class="avatar" style="width: 2.25rem; height: 2.25rem;">
    {(user?.name?.[0] ?? "J").toUpperCase()}
  </div>
</header>
