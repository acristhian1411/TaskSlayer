<script>
  let { data, form } = $props();

  function getTier(reward) {
    if (reward.cost >= 120) return { label: "Epic", accent: "var(--gold)" };
    if (reward.cost >= 40) return { label: "Rare", accent: "var(--rare)" };
    return { label: "Common", accent: "var(--common)" };
  }

  function getRewardDescription(reward) {
    if (reward.durationMinutes && reward.durationMinutes > 0) {
      return `${reward.durationMinutes} min of ${reward.rewardType} unlocked for your next break.`;
    }

    return `Reward type: ${reward.rewardType}. Claim it and enjoy your earned rest.`;
  }

  function formatRedeemedAt(input) {
    if (!input) return "Unknown date";

    const date = new Date(input);
    if (Number.isNaN(date.getTime())) return "Unknown date";

    return date.toLocaleString();
  }
</script>

<div class="page-wrap">
  <div
    style="display: flex; justify-content: space-between; align-items: end; gap: 1rem; flex-wrap: wrap;"
  >
    <div>
      <span class="eyebrow">Vault of Rewards</span>
      <h1 class="page-title">The Treasury</h1>
    </div>

    <article
      class="glass-card"
      style="padding: 1rem 1.35rem; border-radius: 0.8rem; min-width: 220px; text-align: center; box-shadow: 0 0 40px rgba(255, 171, 0, 0.12);"
    >
      <div class="eyebrow" style="color: var(--text-muted);">
        Available Wealth
      </div>
      <div
        style="font-size: 2rem; font-family: 'Epilogue', sans-serif; font-weight: 900; margin-top: 0.35rem;"
      >
        {form?.pointsBalance ?? data.pointsBalance}
        <span style="font-size: 0.8rem; color: var(--gold);">PTS</span>
      </div>
    </article>
  </div>

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

  <section class="reward-grid">
    {#if data.rewards.length === 0}
      <article class="glass-card reward-card" style="grid-column: 1 / -1;">
        <h3 style="margin: 0; font-family: 'Epilogue', sans-serif;">
          No rewards available
        </h3>
        <p style="margin: 0; color: #d7c3ac;">
          Run the rewards seeder to populate the treasury.
        </p>
      </article>
    {:else}
      {#each data.rewards as reward}
        {@const tier = getTier(reward)}
        <article
          class="glass-card reward-card"
          style="border-top: 2px solid {tier.accent};"
        >
          <div
            style="display: flex; justify-content: space-between; align-items: center; gap: 0.6rem;"
          >
            <span
              class="tag"
              style="color: {tier.accent}; border-color: color-mix(in srgb, {tier.accent} 35%, transparent);"
              >{tier.label}</span
            >
            <strong style="color: {tier.accent};">{reward.cost} pts</strong>
          </div>

          <h3
            style="margin: 0; font-family: 'Epilogue', sans-serif; font-size: 1.5rem; line-height: 1.1;"
          >
            {reward.title}
          </h3>
          <p style="margin: 0; color: #d7c3ac;">
            {getRewardDescription(reward)}
          </p>

          <form method="POST" action="?/redeem" style="margin-top: auto;">
            <input type="hidden" name="rewardId" value={reward.id} />
            <button
              class="action-epic"
              type="submit"
              style="width: fit-content; background: {tier.accent}; color: #101010;"
              disabled={reward.cost >
                (form?.pointsBalance ?? data.pointsBalance)}>Redeem</button
            >
          </form>
        </article>
      {/each}
    {/if}
  </section>

  <section
    class="glass-card"
    style="margin-top: 1rem; padding: 1.2rem; border-radius: 0.8rem;"
  >
    <div class="eyebrow" style="color: var(--text-muted);">
      Recent Redemptions
    </div>

    {#if data.redemptions.length === 0}
      <p style="margin: 0.55rem 0 0; color: #d7c3ac;">
        No redemptions yet. Claim your first reward from the vault.
      </p>
    {:else}
      <div style="display: grid; gap: 0.55rem; margin-top: 0.8rem;">
        {#each data.redemptions.slice(0, 5) as redemption}
          <article
            style="display: flex; justify-content: space-between; align-items: center; gap: 0.8rem; border: 1px solid var(--line); border-radius: 0.6rem; padding: 0.65rem 0.8rem; background: rgba(53, 53, 52, 0.35);"
          >
            <div>
              <strong style="font-family: 'Epilogue', sans-serif;"
                >{redemption.rewardName}</strong
              >
              <p
                style="margin: 0.2rem 0 0; color: var(--text-muted); font-size: 0.82rem;"
              >
                {formatRedeemedAt(redemption.redeemedAt)}
              </p>
            </div>
            <strong style="color: var(--gold-soft);"
              >-{redemption.pointsSpent} pts</strong
            >
          </article>
        {/each}
      </div>
    {/if}
  </section>

  <section
    class="glass-card"
    style="margin-top: 1rem; padding: 2rem; border-radius: 0.8rem; text-align: center; opacity: 0.75;"
  >
    <h2
      style="margin: 0; font-family: 'Epilogue', sans-serif; font-size: clamp(1.4rem, 4vw, 2.2rem);"
    >
      Master Artifacts Incoming
    </h2>
    <p class="eyebrow" style="margin-top: 0.6rem; color: var(--text-muted);">
      Level 50 required to unlock master treasury
    </p>
  </section>
</div>
