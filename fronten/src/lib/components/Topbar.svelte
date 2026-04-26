<script>
  let { user = null } = $props();
</script>

<header class="topbar">
  <button class="icon-btn" aria-label="Notifications">◐</button>
  <button class="icon-btn" aria-label="Settings">⚙</button>
  <div class="avatar" style="width: 2.25rem; height: 2.25rem;">
    {(user?.name?.[0] ?? "J").toUpperCase()}
  </div>
</header>
