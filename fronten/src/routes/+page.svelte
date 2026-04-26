<script>
  let { data, form } = $props();

  function getRarity(task) {
    if (task.rewardPoints >= 100)
      return { label: "Epic Quest", color: "var(--gold)" };
    if (task.rewardPoints >= 50)
      return { label: "Rare Quest", color: "var(--rare)" };
    return { label: "Common Quest", color: "var(--common)" };
  }

  function getDifficultyLabel(level) {
    const map = {
      1: "Apprentice - Low",
      2: "Adept - Medium",
      3: "Elite - High",
      4: "Mythic - Extreme",
    };

    return map[level] || `Level ${level}`;
  }

  function timeAgo(input) {
    if (!input) return "Unknown date";

    const date = new Date(input);
    if (Number.isNaN(date.getTime())) return "Unknown date";

    const minutes = Math.floor((Date.now() - date.getTime()) / 60000);

    if (minutes < 1) return "Added just now";
    if (minutes < 60) return `Added ${minutes}m ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `Added ${hours}h ago`;

    const days = Math.floor(hours / 24);
    return `Added ${days}d ago`;
  }

  function getSessionHref(taskId) {
    return `/session?taskId=${taskId}`;
  }
</script>

<div class="page-wrap">
  <span class="eyebrow">The Great Board</span>
  <div
    style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;"
  >
    <h1 class="page-title">Active Quests</h1>
    <a href="/forge" class="action-epic">Start New Quest</a>
  </div>

  <section class="kpi-grid" style="margin-top: 1rem;">
    <article class="glass-card kpi-card">
      <div class="eyebrow" style="color: var(--text-muted);">Active Quests</div>
      <div
        style="font-family: 'Epilogue', sans-serif; font-size: 2.1rem; font-weight: 900; margin-top: 0.35rem;"
      >
        {data.kpis.active}
      </div>
    </article>

    <article
      class="glass-card kpi-card"
      style="border-left-color: var(--common);"
    >
      <div class="eyebrow" style="color: var(--text-muted);">
        Potential Reward
      </div>
      <div
        style="font-family: 'Epilogue', sans-serif; font-size: 2.1rem; font-weight: 900; margin-top: 0.35rem;"
      >
        {data.kpis.potentialReward}
      </div>
    </article>

    <article
      class="glass-card kpi-card"
      style="border-left-color: var(--rare);"
    >
      <div class="eyebrow" style="color: var(--text-muted);">
        Completed Quests
      </div>
      <div
        style="font-family: 'Epilogue', sans-serif; font-size: 2.1rem; font-weight: 900; margin-top: 0.35rem;"
      >
        {data.kpis.completed}
      </div>
    </article>
  </section>

  {#if form?.message}
    <p
      style="margin: 1rem 0 0; padding: 0.65rem; border-radius: 0.55rem; border: 1px solid rgba(191, 218, 255, 0.35); color: var(--common); background: rgba(191, 218, 255, 0.08);"
    >
      {form.message}
    </p>
  {/if}

  {#if data.loadError}
    <p
      style="margin: 1rem 0 0; padding: 0.65rem; border-radius: 0.55rem; border: 1px solid rgba(249, 171, 255, 0.35); color: var(--rare); background: rgba(249, 171, 255, 0.08);"
    >
      {data.loadError}
    </p>
  {/if}

  <section class="task-list">
    {#if data.tasks.length === 0}
      <article
        class="glass-card task-card"
        style="border-left: 3px solid var(--line);"
      >
        <div>
          <h3 style="margin: 0; font-family: 'Epilogue', sans-serif;">
            No active quests yet
          </h3>
          <p style="margin: 0.45rem 0 0; color: #d7c3ac; max-width: 66ch;">
            Head to the Forge and create your first quest.
          </p>
        </div>
      </article>
    {:else}
      {#each data.tasks as task}
        {@const rarity = getRarity(task)}
        <article
          class="glass-card task-card"
          style="border-left: 3px solid {rarity.color};"
        >
          <div>
            <div
              style="display: flex; align-items: center; gap: 0.55rem; flex-wrap: wrap; margin-bottom: 0.5rem;"
            >
              <span
                class="tag"
                style="color: {rarity.color}; border-color: color-mix(in srgb, {rarity.color} 35%, transparent);"
                >{rarity.label}</span
              >
              <span
                style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.12em; font-family: 'Space Grotesk', sans-serif;"
                >{timeAgo(task.createdAt)}</span
              >
              {#if task.status === "completed"}
                <span
                  class="tag"
                  style="color: var(--gold-soft); border-color: rgba(255, 208, 146, 0.4);"
                  >Completed</span
                >
              {/if}
            </div>

            <h3
              style="margin: 0; font-family: 'Epilogue', sans-serif; font-size: clamp(1.15rem, 2vw, 1.85rem);"
            >
              {task.title}
            </h3>
            <p style="margin: 0.45rem 0 0; color: #d7c3ac; max-width: 66ch;">
              {task.detail}
            </p>
          </div>

          <div
            style="text-align: right; min-width: 120px; display: grid; gap: 0.5rem; align-content: start;"
          >
            <div>
              <div
                class="eyebrow"
                style="font-size: 0.58rem; color: var(--text-muted);"
              >
                Difficulty
              </div>
              <strong style="font-size: 0.88rem;"
                >{getDifficultyLabel(task.difficultyLevel)}</strong
              >
            </div>
            <div>
              <div
                class="eyebrow"
                style="font-size: 0.58rem; color: var(--text-muted);"
              >
                Reward
              </div>
              <strong
                style="font-size: 1.2rem; color: {rarity.color}; font-family: 'Epilogue', sans-serif;"
                >{task.rewardPoints} pts</strong
              >
            </div>

            {#if task.status !== "completed"}
              <a href={getSessionHref(task.id)} class="task-link-btn">
                Enter Session
              </a>

              <form method="POST" action="?/complete">
                <input type="hidden" name="taskId" value={task.id} />
                <button
                  type="submit"
                  class="task-link-btn task-link-btn--ghost"
                >
                  Complete
                </button>
              </form>
            {:else}
              <form method="POST" action="?/uncomplete">
                <input type="hidden" name="taskId" value={task.id} />
                <button type="submit" class="task-link-btn task-link-btn--undo">
                  Uncomplete
                </button>
              </form>
            {/if}
          </div>
        </article>
      {/each}
    {/if}
  </section>
</div>
