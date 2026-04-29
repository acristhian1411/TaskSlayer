<script>
  import { browser } from "$app/environment";
  import { goto } from "$app/navigation";
  import { onMount } from "svelte";

  let { data } = $props();
  const initialTask = $derived(data.task || null);
  let taskState = $state(null);

  let elapsedMs = $state(0);
  let isRunning = $state(false);
  let currentExecutionId = $state(null);
  let currentExecutionCheckpointId = $state(null);
  let selectedCheckpointId = $state(null);
  let commandPending = $state(false);
  let sessionError = $state("");
  let sessionNotice = $state("");
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

    return result || {};
  }

  function applyExecutionState(execution) {
    currentExecutionId = execution?.id || null;
    currentExecutionCheckpointId = execution?.checkpointId || null;
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
    if (!taskState?.id || commandPending) return;

    commandPending = true;
    sessionError = "";

    try {
      if (!currentExecutionId) {
        const response = await runSessionCommand("start", {
          taskId: taskState.id,
          startedAt: new Date().toISOString(),
          checkpointId: selectedCheckpointId,
        });
        const execution = response?.data || null;
        applyExecutionState(execution);
        return;
      }

      if (isRunning) {
        syncElapsed();
        const response = await runSessionCommand("pause", {
          executionId: currentExecutionId,
        });
        const execution = response?.data || null;
        applyExecutionState(execution);
        return;
      }

      const response = await runSessionCommand("resume", {
        executionId: currentExecutionId,
      });
      const execution = response?.data || null;
      applyExecutionState(execution);
    } catch (error) {
      sessionError = error?.message || "Could not update quest session.";
    } finally {
      commandPending = false;
    }
  }

  async function setCheckpointCompleted(checkpointId, completed) {
    if (!taskState?.id || !checkpointId || commandPending) return;

    if (isRunning && currentExecutionCheckpointId === checkpointId) {
      sessionError = "Pause this checkpoint timer before changing its status.";
      return;
    }

    commandPending = true;
    sessionError = "";
    sessionNotice = "";

    try {
      const response = await runSessionCommand(
        completed ? "completeCheckpoint" : "uncompleteCheckpoint",
        {
          taskId: taskState.id,
          checkpointId,
        },
      );

      if (response?.task) {
        taskState = response.task;

        if (!completed) {
          selectedCheckpointId = checkpointId;
          sessionNotice = "Checkpoint reopened and focused.";
        }
      }
    } catch (error) {
      sessionError =
        error?.message || "Could not update this checkpoint right now.";
    } finally {
      commandPending = false;
    }
  }

  function focusCheckpoint(checkpointId) {
    if (!checkpointId || commandPending) return;

    selectedCheckpointId = checkpointId;
    sessionError = "";
    sessionNotice = "Checkpoint focused.";
  }

  async function slayBoss() {
    if (!taskState?.id || commandPending) return;

    if (!allCheckpointsCompleted) {
      sessionError = "Complete all checkpoints before slaying the final boss.";
      return;
    }

    commandPending = true;
    sessionError = "";

    try {
      if (currentExecutionId) {
        if (isRunning) {
          syncElapsed();
        }

        const response = await runSessionCommand("completeExecution", {
          executionId: currentExecutionId,
          endedAt: new Date().toISOString(),
        });

        if (response?.task) {
          taskState = response.task;
        }
      } else {
        const response = await runSessionCommand("completeTask", {
          taskId: taskState.id,
        });

        if (response?.task) {
          taskState = response.task;
        }
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
    if (!browser || !taskState?.id) return;

    applyExecutionState(data.execution || null);

    const firstPending = (taskState?.checkpoints || []).find(
      (checkpoint) => !checkpoint.isCompleted,
    );
    selectedCheckpointId = firstPending?.id || null;

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
    taskState ? getDifficultyLabel(taskState.difficultyLevel) : "Unknown",
  );
  const encounterLabel = $derived(
    taskState ? getEncounterLabel(taskState.difficultyLevel) : "Unknown",
  );
  const checkpoints = $derived(taskState?.checkpoints || []);
  const completedCheckpoints = $derived(
    checkpoints.filter((checkpoint) => checkpoint.isCompleted).length,
  );
  const allCheckpointsCompleted = $derived(
    checkpoints.length === 0 || completedCheckpoints === checkpoints.length,
  );
  const bossProgress = $derived(
    checkpoints.length === 0
      ? taskState
        ? getBossProgress(taskState.difficultyLevel)
        : 0
      : Math.round((completedCheckpoints / checkpoints.length) * 100),
  );
  const selectedCheckpoint = $derived(
    checkpoints.find((checkpoint) => checkpoint.id === selectedCheckpointId) ||
      null,
  );
  const actionLabel = $derived(
    currentExecutionId
      ? isRunning
        ? "Pause Quest"
        : "Resume Quest"
      : selectedCheckpoint
        ? "Start Checkpoint"
        : "Start Boss Fight",
  );
  const actionIcon = $derived(
    currentExecutionId ? (isRunning ? "II" : ">") : ">",
  );
  const questStatusLabel = $derived(
    currentExecutionId ? (isRunning ? "running" : "paused") : "not started",
  );

  $effect(() => {
    if (taskState === null && initialTask) {
      taskState = initialTask;
    }
  });

  $effect(() => {
    if (!taskState?.checkpoints?.length) {
      selectedCheckpointId = null;
      return;
    }

    const selectedStillPending = taskState.checkpoints.some(
      (checkpoint) =>
        checkpoint.id === selectedCheckpointId && !checkpoint.isCompleted,
    );

    if (selectedStillPending) {
      return;
    }

    const firstPending = taskState.checkpoints.find(
      (checkpoint) => !checkpoint.isCompleted,
    );

    selectedCheckpointId = firstPending?.id || null;
  });
</script>

{#if data.loadError || !taskState}
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
          <span class="eyebrow"
            >Reward: {taskState.rewardPoints} pts ({taskState.bossRewardPoints}
            boss / {taskState.checkpointRewardTotal} checkpoints)</span
          >
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
        <p class="boss-session__eyebrow">Raid Route</p>

        <h1 class="boss-session__title">
          {taskState.title}
          <span>{encounterLabel} Quest</span>
        </h1>

        <p class="boss-session__description">{taskState.detail}</p>

        <div class="raid-path glass-card">
          {#if checkpoints.length === 0}
            <div class="raid-path__empty">
              <p>
                No checkpoints for this quest. You can go straight to the boss.
              </p>
            </div>
          {:else}
            {#each checkpoints as checkpoint, index}
              <article
                class="raid-node"
                class:raid-node--completed={checkpoint.isCompleted}
                class:raid-node--active={!checkpoint.isCompleted &&
                  selectedCheckpointId === checkpoint.id}
              >
                <div class="raid-node__step">CP {index + 1}</div>
                <h3>{checkpoint.title}</h3>
                {#if !checkpoint.isCompleted && selectedCheckpointId === checkpoint.id}
                  <p class="raid-node__focus">Focused checkpoint</p>
                {/if}
                <p>Reward: +{checkpoint.rewardPointsSmall} pts</p>
                <div class="raid-node__actions">
                  {#if !checkpoint.isCompleted}
                    <button
                      type="button"
                      class="session-btn session-btn--pause"
                      disabled={commandPending}
                      onclick={() => focusCheckpoint(checkpoint.id)}
                    >
                      Focus
                    </button>
                    <button
                      type="button"
                      class="session-btn session-btn--slay"
                      disabled={commandPending}
                      onclick={() => {
                        setCheckpointCompleted(checkpoint.id, true);
                      }}
                    >
                      Clear
                    </button>
                  {:else}
                    <button
                      type="button"
                      class="session-btn session-btn--pause"
                      disabled={commandPending}
                      onclick={() => {
                        setCheckpointCompleted(checkpoint.id, false);
                      }}
                    >
                      Reopen
                    </button>
                  {/if}
                </div>
              </article>
            {/each}
          {/if}

          <article
            class="raid-node raid-node--boss"
            class:raid-node--completed={allCheckpointsCompleted}
          >
            <div class="raid-node__step">FINAL</div>
            <h3>Boss Gate</h3>
            <p>
              {#if allCheckpointsCompleted}
                Path unlocked. Finish with +{taskState.bossRewardPoints} pts.
              {:else}
                Locked until all checkpoints are cleared.
              {/if}
            </p>
          </article>
        </div>

        {#if sessionError}
          <p class="boss-session__error">{sessionError}</p>
        {/if}

        {#if sessionNotice}
          <p class="boss-session__notice">{sessionNotice}</p>
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
          disabled={commandPending || !allCheckpointsCompleted}
          onclick={slayBoss}
        >
          <span class="session-btn__icon" aria-hidden="true">X</span>
          <span
            >{commandPending
              ? "Finishing..."
              : allCheckpointsCompleted
                ? "Slay Boss"
                : "Boss Locked"}</span
          >
        </button>
      </div>

      <footer class="boss-session__meta">
        <div class="boss-buff glass-card">
          <span class="boss-buff__badge" aria-hidden="true"
            >+{taskState.rewardPoints}</span
          >
          <div>
            <p class="boss-buff__label">Active Quest</p>
            <p class="boss-buff__value">
              {difficultyLabel} difficulty, status: {questStatusLabel},
              checkpoints:
              {completedCheckpoints}/{checkpoints.length}
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
