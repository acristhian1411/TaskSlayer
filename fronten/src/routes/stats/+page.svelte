<script>
  let { data } = $props();

  function formatHours(seconds) {
    const hours = seconds / 3600;
    return `${hours.toFixed(1)} h`;
  }

  function formatPercent(rate) {
    return `${(rate * 100).toFixed(1)}%`;
  }

  function formatDate(day) {
    if (!day) return "Unknown day";

    const date = new Date(day);
    if (Number.isNaN(date.getTime())) return day;

    return date.toLocaleDateString();
  }

  function maxDuration(items) {
    return Math.max(...items.map((item) => item.totalDurationSeconds), 1);
  }

  function barWidth(value, items) {
    return (value / maxDuration(items)) * 100;
  }
</script>

<div class="page-wrap">
  <span class="eyebrow">Battle Analytics</span>
  <h1 class="page-title">Stats</h1>

  {#if data.loadError}
    <p
      style="margin: 1rem 0 0; padding: 0.65rem; border-radius: 0.55rem; border: 1px solid rgba(249, 171, 255, 0.35); color: var(--rare); background: rgba(249, 171, 255, 0.08);"
    >
      {data.loadError}
    </p>
  {/if}

  <section class="kpi-grid" style="margin-top: 1rem;">
    <article class="glass-card kpi-card">
      <div class="eyebrow" style="color: var(--text-muted);">
        Total Sessions
      </div>
      <div
        style="font-family: 'Epilogue', sans-serif; font-size: 2.1rem; font-weight: 900; margin-top: 0.35rem;"
      >
        {data.summary.totalSessions}
      </div>
    </article>

    <article
      class="glass-card kpi-card"
      style="border-left-color: var(--common);"
    >
      <div class="eyebrow" style="color: var(--text-muted);">
        Completion Rate
      </div>
      <div
        style="font-family: 'Epilogue', sans-serif; font-size: 2.1rem; font-weight: 900; margin-top: 0.35rem; color: var(--common);"
      >
        {formatPercent(data.summary.completionRate)}
      </div>
    </article>

    <article
      class="glass-card kpi-card"
      style="border-left-color: var(--gold);"
    >
      <div class="eyebrow" style="color: var(--text-muted);">Focus Time</div>
      <div
        style="font-family: 'Epilogue', sans-serif; font-size: 2.1rem; font-weight: 900; margin-top: 0.35rem; color: var(--gold-soft);"
      >
        {formatHours(data.summary.totalDurationSeconds)}
      </div>
    </article>
  </section>

  <section
    class="glass-card"
    style="margin-top: 1rem; padding: 1.1rem; border-radius: 0.8rem;"
  >
    <div class="eyebrow" style="color: var(--text-muted);">Points Economy</div>
    <div
      style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.7rem; margin-top: 0.7rem;"
    >
      <article
        style="border: 1px solid var(--line); border-radius: 0.6rem; padding: 0.7rem; background: rgba(53, 53, 52, 0.35);"
      >
        <div
          class="eyebrow"
          style="font-size: 0.58rem; color: var(--text-muted);"
        >
          Earned
        </div>
        <strong style="font-size: 1.05rem; color: var(--common);"
          >{data.points.earnedPoints}</strong
        >
      </article>
      <article
        style="border: 1px solid var(--line); border-radius: 0.6rem; padding: 0.7rem; background: rgba(53, 53, 52, 0.35);"
      >
        <div
          class="eyebrow"
          style="font-size: 0.58rem; color: var(--text-muted);"
        >
          Spent
        </div>
        <strong style="font-size: 1.05rem; color: var(--rare);"
          >{data.points.spentPoints}</strong
        >
      </article>
      <article
        style="border: 1px solid var(--line); border-radius: 0.6rem; padding: 0.7rem; background: rgba(53, 53, 52, 0.35);"
      >
        <div
          class="eyebrow"
          style="font-size: 0.58rem; color: var(--text-muted);"
        >
          Balance
        </div>
        <strong style="font-size: 1.05rem; color: var(--gold-soft);"
          >{data.points.balance}</strong
        >
      </article>
      <article
        style="border: 1px solid var(--line); border-radius: 0.6rem; padding: 0.7rem; background: rgba(53, 53, 52, 0.35);"
      >
        <div
          class="eyebrow"
          style="font-size: 0.58rem; color: var(--text-muted);"
        >
          Ledger Entries
        </div>
        <strong style="font-size: 1.05rem;">{data.points.entriesCount}</strong>
      </article>
    </div>
  </section>

  <section
    class="glass-card"
    style="margin-top: 1rem; padding: 1.1rem; border-radius: 0.8rem;"
  >
    <div class="eyebrow" style="color: var(--text-muted);">Time by Quest</div>

    {#if data.timeByTask.length === 0}
      <p style="margin: 0.65rem 0 0; color: #d7c3ac;">No execution data yet.</p>
    {:else}
      <div style="display: grid; gap: 0.65rem; margin-top: 0.75rem;">
        {#each data.timeByTask.slice(0, 8) as item}
          <article
            style="border: 1px solid var(--line); border-radius: 0.6rem; padding: 0.7rem; background: rgba(53, 53, 52, 0.35);"
          >
            <div
              style="display: flex; justify-content: space-between; gap: 0.8rem; align-items: baseline;"
            >
              <strong style="font-family: 'Epilogue', sans-serif;"
                >{item.title}</strong
              >
              <span style="font-size: 0.8rem; color: var(--text-muted);"
                >{item.sessions} sessions</span
              >
            </div>
            <div class="hp-track" style="height: 8px; margin: 0.55rem 0 0;">
              <div
                class="hp-fill"
                style="width: {barWidth(
                  item.totalDurationSeconds,
                  data.timeByTask,
                )}%;"
              ></div>
            </div>
            <div
              style="margin-top: 0.35rem; font-size: 0.8rem; color: var(--gold-soft);"
            >
              {formatHours(item.totalDurationSeconds)}
            </div>
          </article>
        {/each}
      </div>
    {/if}
  </section>

  <section
    class="glass-card"
    style="margin-top: 1rem; padding: 1.1rem; border-radius: 0.8rem;"
  >
    <div class="eyebrow" style="color: var(--text-muted);">
      Daily Productivity
    </div>

    {#if data.dailyProductivity.length === 0}
      <p style="margin: 0.65rem 0 0; color: #d7c3ac;">
        No daily history available.
      </p>
    {:else}
      <div style="display: grid; gap: 0.65rem; margin-top: 0.75rem;">
        {#each data.dailyProductivity.slice(-10).reverse() as day}
          <article
            style="border: 1px solid var(--line); border-radius: 0.6rem; padding: 0.7rem; background: rgba(53, 53, 52, 0.35);"
          >
            <div
              style="display: flex; justify-content: space-between; gap: 0.8rem; align-items: baseline;"
            >
              <strong style="font-family: 'Epilogue', sans-serif;"
                >{formatDate(day.day)}</strong
              >
              <span style="font-size: 0.8rem; color: var(--text-muted);"
                >{day.sessions} sessions</span
              >
            </div>
            <div class="hp-track" style="height: 8px; margin: 0.55rem 0 0;">
              <div
                class="hp-fill"
                style="width: {barWidth(
                  day.totalDurationSeconds,
                  data.dailyProductivity,
                )}%; background: linear-gradient(90deg, #bfdaff, #d9ecff);"
              ></div>
            </div>
            <div
              style="margin-top: 0.35rem; font-size: 0.8rem; color: var(--common);"
            >
              {formatHours(day.totalDurationSeconds)}
            </div>
          </article>
        {/each}
      </div>
    {/if}
  </section>
</div>
