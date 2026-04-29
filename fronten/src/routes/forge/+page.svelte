<script>
  let { form } = $props();

  let inputTask = $state("Update user authentication logic...");
  let checkpointDraft = $state("");
  let checkpointItems = $state([]);
  let isForging = $state(false);
  let forgeError = $state("");
  let forgeNotice = $state("");
  let engineStatus = $state("Ready to query LM Studio API");
  let engineBackend = $state("lmstudio");
  let forged = $state({
    titleRpg: "UNNAMED QUEST",
    description: "A quest forged from your mission.",
    difficultyLevel: 1,
    rewardPoints: 10,
    tags: ["Focus"],
  });

  function extractErrorMessage(error) {
    if (!error) return "Unknown error";
    if (typeof error === "string") return error;
    if (typeof error === "object" && "message" in error) {
      return String(error.message || "Unknown error");
    }
    return "Unknown error";
  }

  function clampDifficulty(level) {
    return Math.min(4, Math.max(1, Number(level) || 1));
  }

  function rewardForLevel(level) {
    const map = { 1: 10, 2: 30, 3: 60, 4: 120 };
    return map[clampDifficulty(level)] || 10;
  }

  function buildDescriptionWithSource(originalTask, narrative) {
    const source = String(originalTask || "").trim();
    const details = String(narrative || "").trim();

    if (!source && !details) {
      return "Take on this mission with precision and claim your reward.";
    }

    if (!source) {
      return details;
    }

    if (!details) {
      return `Original objective: ${source}`;
    }

    return [
      `Original objective: ${source}`,
      "",
      `Battle plan: ${details}`,
    ].join("\n");
  }

  function inferDifficultyFromText(task) {
    const text = task.toLowerCase();
    let level = 1;

    if (text.length > 70) level = 2;
    if (text.length > 130) level = 3;

    if (
      /(migrate|infra|security|auth|arquitectura|architecture|performance|optimizer|distributed|refactor)/.test(
        text,
      )
    ) {
      level += 1;
    }

    return clampDifficulty(level);
  }

  function fallbackTransform(task) {
    const difficulty = inferDifficultyFromText(task);
    const reward = rewardForLevel(difficulty);

    return {
      titleRpg: `The ${task.split(" ").slice(0, 4).join(" ").toUpperCase()}`,
      description: buildDescriptionWithSource(
        task,
        "Complete it with precision and claim your reward.",
      ),
      difficultyLevel: difficulty,
      rewardPoints: reward,
      tags: ["Focus", "Execution"],
    };
  }

  function normalizeModelOutput(raw, originalTask) {
    if (!raw) return fallbackTransform(originalTask);

    const difficulty = clampDifficulty(raw.difficulty_level);
    const reward = [10, 30, 60, 120].includes(Number(raw.reward_points))
      ? Number(raw.reward_points)
      : rewardForLevel(difficulty);

    const tags = Array.isArray(raw.tags)
      ? raw.tags
          .map((tag) => String(tag).trim())
          .filter(Boolean)
          .slice(0, 4)
      : [];

    return {
      titleRpg:
        String(raw.titleRpg || raw.boss_name || "").trim() ||
        fallbackTransform(originalTask).titleRpg,
      description: buildDescriptionWithSource(
        originalTask,
        String(raw.description || raw.narrative || "").trim(),
      ),
      difficultyLevel: difficulty,
      rewardPoints: reward,
      tags: tags.length > 0 ? tags : ["Focus"],
    };
  }

  async function forgeTask() {
    forgeError = "";
    forgeNotice = "";

    if (!inputTask.trim()) {
      forgeError = "Write an original task first.";
      return;
    }

    isForging = true;
    engineStatus = "Querying LM Studio API...";

    try {
      const response = await fetch("/forge/generate", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          task: inputTask.trim(),
          checkpoints: buildCheckpointPayload(checkpointItems),
        }),
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload?.ok) {
        throw new Error(payload?.error || "LM Studio request failed");
      }

      forged = normalizeModelOutput(payload?.data, inputTask.trim());
      engineStatus = "Quest generated through LM Studio API";
    } catch (error) {
      forged = fallbackTransform(inputTask.trim());

      const message = extractErrorMessage(error);
      console.error("[Forge] LM Studio generation failed:", error);

      engineStatus = "LM Studio unavailable. Deterministic forge active.";

      forgeNotice = `Could not generate with LM Studio API. Reason: ${message}. Applied deterministic forge.`;
    } finally {
      isForging = false;
    }
  }

  function threatLabel(level) {
    const map = {
      1: "Escaramuza",
      2: "Duelo",
      3: "Boss Raid",
      4: "World Boss",
    };

    return map[clampDifficulty(level)] || "Escaramuza";
  }

  function threatBar(level) {
    return `${clampDifficulty(level) * 25}%`;
  }

  function addCheckpoint() {
    const title = checkpointDraft.trim();

    if (!title) {
      return;
    }

    checkpointItems = [...checkpointItems, title];
    checkpointDraft = "";
  }

  function removeCheckpoint(index) {
    checkpointItems = checkpointItems.filter(
      (_, itemIndex) => itemIndex !== index,
    );
  }

  function updateCheckpoint(index, value) {
    checkpointItems = checkpointItems.map((item, itemIndex) =>
      itemIndex === index ? value : item,
    );
  }

  function buildCheckpointPayload(values) {
    return values
      .map((title) => String(title || "").trim())
      .filter(Boolean)
      .map((title) => ({ title }));
  }

  const checkpointsPayloadJson = $derived(
    JSON.stringify(buildCheckpointPayload(checkpointItems)),
  );

  const checkpointCount = $derived(
    buildCheckpointPayload(checkpointItems).length,
  );
</script>

<div class="page-wrap">
  <div style="text-align: center; margin-bottom: 1rem;">
    <h1 class="page-title" style="font-size: clamp(2rem, 4vw, 3.5rem);">
      The Forge
    </h1>
    <p class="eyebrow" style="color: var(--text-muted);">
      LM Studio API transmutation engine
    </p>
  </div>

  {#if form?.message}
    <p
      style="margin: 0 0 1rem; padding: 0.65rem; border-radius: 0.55rem; border: 1px solid rgba(191, 218, 255, 0.35); color: var(--common); background: rgba(191, 218, 255, 0.08);"
    >
      {form.message}
    </p>
  {/if}

  {#if forgeError}
    <p
      style="margin: 0 0 1rem; padding: 0.65rem; border-radius: 0.55rem; border: 1px solid rgba(249, 171, 255, 0.35); color: var(--rare); background: rgba(249, 171, 255, 0.08);"
    >
      {forgeError}
    </p>
  {/if}

  {#if forgeNotice}
    <p
      style="margin: 0 0 1rem; padding: 0.65rem; border-radius: 0.55rem; border: 1px solid rgba(191, 218, 255, 0.35); color: var(--common); background: rgba(191, 218, 255, 0.08);"
    >
      {forgeNotice}
    </p>
  {/if}

  <section class="forge-grid">
    <article
      class="glass-card forge-box"
      style="box-shadow: 0 0 50px rgba(255, 171, 0, 0.14);"
    >
      <label class="eyebrow" for="quest">Original Task</label>
      <textarea id="quest" class="forge-input" bind:value={inputTask}
      ></textarea>

      <div style="display: flex; justify-content: center; margin-top: 1rem;">
        <button
          class="action-epic"
          type="button"
          onclick={forgeTask}
          disabled={isForging}
        >
          {isForging ? "Forging..." : "Forge Reality"}
        </button>
      </div>

      <p
        style="margin: 0.75rem 0 0; font-size: 0.78rem; color: var(--text-muted);"
      >
        {engineStatus}
      </p>

      <label
        class="eyebrow"
        for="checkpointDraft"
        style="margin-top: 1rem; display: block;">Raid Checkpoints</label
      >
      <div class="forge-checkpoint-entry">
        <input
          id="checkpointDraft"
          class="forge-input forge-input--compact"
          type="text"
          bind:value={checkpointDraft}
          placeholder="Add subtask checkpoint..."
          onkeydown={(event) => {
            if (event.key === "Enter") {
              event.preventDefault();
              addCheckpoint();
            }
          }}
        />
        <button class="task-link-btn" type="button" onclick={addCheckpoint}>
          Add
        </button>
      </div>

      {#if checkpointItems.length > 0}
        <div class="forge-checkpoint-list">
          {#each checkpointItems as item, index}
            <div class="forge-checkpoint-item">
              <span class="tag">CP {index + 1}</span>
              <input
                class="forge-input forge-input--compact"
                type="text"
                value={item}
                oninput={(event) =>
                  updateCheckpoint(index, event.currentTarget.value)}
              />
              <button
                class="task-link-btn task-link-btn--undo"
                type="button"
                onclick={() => removeCheckpoint(index)}
              >
                Remove
              </button>
            </div>
          {/each}
        </div>
      {/if}

      <p
        style="margin: 0.65rem 0 0; font-size: 0.78rem; color: var(--text-muted);"
      >
        {#if checkpointCount > 0}
          {checkpointCount} subtasks added. Checkpoints grant 20% of total points;
          final boss grants 80%.
        {:else}
          Add subtasks if this quest is a Raid.
        {/if}
      </p>
    </article>

    <article class="glass-card forge-box">
      <div class="eyebrow" style="color: var(--text-muted);">Threat Level</div>
      <h3
        style="margin: 0.3rem 0 0.75rem; font-family: 'Epilogue', sans-serif; color: var(--rare);"
      >
        {threatLabel(forged.difficultyLevel)}
      </h3>
      <div class="hp-track" style="height: 8px;">
        <div
          class="hp-fill"
          style="width: {threatBar(
            forged.difficultyLevel,
          )}; background: linear-gradient(90deg, #f9abff, #ffd6fe);"
        ></div>
      </div>
      <p
        style="color: var(--text-muted); font-size: 0.88rem; margin: 0.8rem 0 0;"
      >
        Loot: {forged.rewardPoints} pts
      </p>
    </article>
  </section>

  <section
    class="glass-card"
    style="margin-top: 1rem; padding: 1.2rem; border-radius: 0.8rem;"
  >
    <div class="eyebrow" style="color: var(--text-muted);">
      Transmutation Chamber
    </div>
    <h2
      style="margin: 0.4rem 0; font-family: 'Epilogue', sans-serif; color: var(--gold-soft);"
    >
      {forged.titleRpg}
    </h2>
    <p style="margin: 0; color: #d7c3ac; white-space: pre-line;">
      {forged.description}
    </p>

    <div
      style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.8rem;"
    >
      {#each forged.tags as tag}
        <span class="tag">{tag}</span>
      {/each}
    </div>

    <form
      method="POST"
      action="?/create"
      style="margin-top: 1rem; display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;"
    >
      <input type="hidden" name="title_original" value={inputTask} />
      <input type="hidden" name="title_rpg" value={forged.titleRpg} />
      <input type="hidden" name="description" value={forged.description} />
      <input
        type="hidden"
        name="difficulty_level"
        value={forged.difficultyLevel}
      />
      <input type="hidden" name="reward_points" value={forged.rewardPoints} />
      <input
        type="hidden"
        name="checkpoints_json"
        value={checkpointsPayloadJson}
      />

      <button
        class="action-epic"
        type="submit"
        disabled={isForging || !inputTask.trim()}
      >
        Save Quest
      </button>
      <span style="font-size: 0.8rem; color: var(--text-muted);"
        >This creates a pending quest in your board.</span
      >
    </form>
  </section>
</div>
