<script>
  import { browser } from "$app/environment";
  import { goto } from "$app/navigation";
  import { onMount } from "svelte";

  let { data } = $props();

  let elapsedMs = $state(0);
  let isRunning = $state(false);
  let currentExecutionId = $state(null);
  let commandPending = $state(false);
  let sessionError = $state("");
  let intervalId = null;
  let startedAt = 0;

  function getDifficultyLabel(level) {
    const map = {
      1: "Apprentice",
      2: "Adept",
      3: "Elite",
      4: "Mythic",
    };

    return map[level] || `Level ${level}`;
  }

  function getEncounterLabel(level) {
    if (level >= 4) return "Legendary";
    if (level === 3) return "Epic";
    if (level === 2) return "Rare";
    return "Common";
  }

  function getBossProgress(level) {
    if (level >= 4) return 82;
    if (level === 3) return 65;
    if (level === 2) return 48;
    return 32;
  }

  function isExecutionRunning(execution) {
    return Boolean(
      execution?.id &&
        execution?.endedAt === null &&
        execution?.latestLifecycleType !== "pause",
    );
  }

  function getExecutionElapsedMs(execution) {
    if (!execution?.id) return 0;

    const baseMs = Math.max(0, Number(execution.durationSeconds || 0) * 1000);

    if (!isExecutionRunning(execution)) {
      return baseMs;
    }

    const lastRunningAt = execution?.lastRunningAt || execution?.startedAt;
    const runningFrom = new Date(lastRunningAt || 0).getTime();

    if (!Number.isFinite(runningFrom) || runningFrom <= 0) {
      return baseMs;
    }

    return baseMs + Math.max(0, Date.now() - runningFrom);
  }

  async function runSessionCommand(action, payload = {}) {
    const response = await fetch("/session/command", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ action, ...payload }),
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(result?.error || "Could not update quest session.");
    }

    return result?.data || null;
  }

  function applyExecutionState(execution) {
    currentExecutionId = execution?.id || null;
    elapsedMs = getExecutionElapsedMs(execution);
    isRunning = isExecutionRunning(execution);

    if (isRunning) {
      startedAt = Date.now() - elapsedMs;
      startTicking();
      return;
    }

    stopTicking();
  }

  function stopTicking() {
    if (intervalId) {
      clearInterval(intervalId);
      intervalId = null;
    }
  }

  function syncElapsed() {
    if (!isRunning) return;
    elapsedMs = Math.max(0, Date.now() - startedAt);
  }

  function startTicking() {
    stopTicking();
    intervalId = setInterval(() => {
      syncElapsed();
    }, 1000);
  }

  async function toggleTimer() {
    if (!data.task?.id || commandPending) return;

    commandPending = true;
    sessionError = "";

    try {
      if (!currentExecutionId) {
        const execution = await runSessionCommand("start", {
          taskId: data.task.id,
          startedAt: new Date().toISOString(),
        });
        applyExecutionState(execution);
        return;
      }

      if (isRunning) {
        syncElapsed();
        const execution = await runSessionCommand("pause", {
          executionId: currentExecutionId,
        });
        applyExecutionState(execution);
        return;
      }

      const execution = await runSessionCommand("resume", {
        executionId: currentExecutionId,
      });
      applyExecutionState(execution);
    } catch (error) {
      sessionError = error?.message || "Could not update quest session.";
    } finally {
      commandPending = false;
    }
  }

  async function slayBoss() {
    if (!data.task?.id || commandPending) return;

    commandPending = true;
    sessionError = "";

    try {
      if (currentExecutionId) {
        if (isRunning) {
          syncElapsed();
        }

        await runSessionCommand("completeExecution", {
          executionId: currentExecutionId,
          endedAt: new Date().toISOString(),
        });
      } else {
        await runSessionCommand("completeTask", {
          taskId: data.task.id,
        });
      }

      stopTicking();
      isRunning = false;
      await goto("/");
    } catch (error) {
      sessionError = error?.message || "Could not complete this quest.";
    } finally {
      commandPending = false;
    }
  }

  onMount(() => {
    if (!browser || !data.task?.id) return;

    applyExecutionState(data.execution || null);

    return () => {
      if (isRunning) {
        syncElapsed();
      }

      stopTicking();
    };
  });

  function formatTime(value) {
    const totalSeconds = Math.floor(value / 1000);
    const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, "0");
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(
      2,
      "0",
    );
    const seconds = String(totalSeconds % 60).padStart(2, "0");

    return { hours, minutes, seconds };
  }

  const formattedTime = $derived(formatTime(elapsedMs));
  const difficultyLabel = $derived(
    data.task ? getDifficultyLabel(data.task.difficultyLevel) : "Unknown",
  );
  const encounterLabel = $derived(
    data.task ? getEncounterLabel(data.task.difficultyLevel) : "Unknown",
  );
  const bossProgress = $derived(
    data.task ? getBossProgress(data.task.difficultyLevel) : 0,
  );
  const actionLabel = $derived(
    currentExecutionId
      ? isRunning
        ? "Pause Quest"
        : "Resume Quest"
      : "Start Quest",
  );
  const actionIcon = $derived(
    currentExecutionId ? (isRunning ? "II" : ">") : ">",
  );
  const questStatusLabel = $derived(
    currentExecutionId ? (isRunning ? "running" : "paused") : "not started",
  );
</script>

{#if data.loadError || !data.task}
  <section class="boss-session">
    <div class="boss-session__aurora boss-session__aurora--left"></div>
    <div class="boss-session__aurora boss-session__aurora--right"></div>

    <div class="boss-session__inner boss-session__inner--empty">
      <div class="glass-card boss-session__empty-state">
        <p class="boss-session__eyebrow">Session Unavailable</p>
        <h1 class="boss-session__title boss-session__title--compact">
          No quest selected
        </h1>
        <p class="boss-session__description">{data.loadError}</p>
        <a
          href="/"
          class="session-btn session-btn--slay boss-session__back-link"
          >Back to Quests</a
        >
      </div>
    </div>
  </section>
{:else}
  <section class="boss-session">
    <div class="boss-session__aurora boss-session__aurora--left"></div>
    <div class="boss-session__aurora boss-session__aurora--right"></div>

    <div class="boss-session__inner">
      <header class="boss-session__status">
        <div class="boss-session__status-copy">
          <span class="eyebrow">Target Difficulty: {difficultyLabel}</span>
          <span class="eyebrow">Reward: {data.task.rewardPoints} pts</span>
        </div>

        <div
          class="boss-health"
          role="progressbar"
          aria-label="Boss health"
          aria-valuemin="0"
          aria-valuemax="100"
          aria-valuenow={bossProgress}
        >
          <div class="boss-health__fill" style={`width: ${bossProgress}%;`}>
            <div class="boss-health__sheen"></div>
            <div class="boss-health__edge"></div>
          </div>
        </div>
      </header>

      <div class="boss-session__center">
        <p class="boss-session__eyebrow">Encountering</p>

        <h1 class="boss-session__title">
          {data.task.title}
          <span>{encounterLabel} Quest</span>
        </h1>

        <p class="boss-session__description">{data.task.detail}</p>

        {#if sessionError}
          <p class="boss-session__error">{sessionError}</p>
        {/if}

        <div class="boss-timer glass-card">
          <span class="boss-timer__label">Session Timer</span>
          <p class="boss-timer__value">
            {formattedTime.hours}:{formattedTime.minutes}:<span
              >{formattedTime.seconds}</span
            >
          </p>
        </div>
      </div>

      <div class="boss-session__actions">
        <button
          class="session-btn session-btn--pause"
          type="button"
          disabled={commandPending}
          onclick={toggleTimer}
        >
          <span class="session-btn__icon" aria-hidden="true">{actionIcon}</span>
          <span>{commandPending ? "Syncing..." : actionLabel}</span>
        </button>

        <button
          class="session-btn session-btn--slay"
          type="button"
          disabled={commandPending}
          onclick={slayBoss}
        >
          <span class="session-btn__icon" aria-hidden="true">X</span>
          <span>{commandPending ? "Finishing..." : "Slay Boss"}</span>
        </button>
      </div>

      <footer class="boss-session__meta">
        <div class="boss-buff glass-card">
          <span class="boss-buff__badge" aria-hidden="true"
            >+{data.task.rewardPoints}</span
          >
          <div>
            <p class="boss-buff__label">Active Quest</p>
            <p class="boss-buff__value">
              {difficultyLabel} difficulty, status: {questStatusLabel}
            </p>
          </div>
        </div>

        <div class="boss-intensity">
          <div class="boss-intensity__dots" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
          </div>
          <p class="boss-buff__label">Session Intensity: {encounterLabel}</p>
        </div>
      </footer>
    </div>
  </section>
{/if}
