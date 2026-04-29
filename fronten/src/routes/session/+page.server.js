import { fetchBackend } from '$lib/server/api';

function mapTask(task) {
  const checkpoints = Array.isArray(task?.checkpoints)
    ? task.checkpoints
        .map((checkpoint) => ({
          id: Number(checkpoint?.id || 0),
          title: String(checkpoint?.title || 'Checkpoint'),
          isCompleted: Boolean(checkpoint?.is_completed),
          orderIndex: Number(checkpoint?.order_index || 0),
          rewardPointsSmall: Number(checkpoint?.reward_points_small || 0)
        }))
        .filter((checkpoint) => checkpoint.id > 0)
        .sort((left, right) => left.orderIndex - right.orderIndex)
    : [];

  const checkpointRewardTotal = checkpoints.reduce(
    (sum, checkpoint) => sum + checkpoint.rewardPointsSmall,
    0
  );

  return {
    id: Number(task?.id || 0),
    title: task?.title_rpg || task?.title_original || 'Unknown Quest',
    detail: task?.description || 'No quest details available.',
    difficultyLevel: Number(task?.difficulty_level || 1),
    rewardPoints: Number(task?.reward_points || 0),
    hasCheckpoints: Boolean(task?.has_checkpoints),
    checkpoints,
    checkpointRewardTotal,
    bossRewardPoints: Math.max(0, Number(task?.reward_points || 0) - checkpointRewardTotal),
    status: String(task?.status || 'pending'),
    createdAt: task?.created_at || null
  };
}

function getLatestLifecycleEvent(events = []) {
  return [...events]
    .filter((event) => ['start', 'pause', 'resume', 'stop', 'complete'].includes(event?.type))
    .sort((left, right) => {
      const leftTime = new Date(left?.timestamp || 0).getTime();
      const rightTime = new Date(right?.timestamp || 0).getTime();

      if (leftTime === rightTime) {
        return Number(right?.id || 0) - Number(left?.id || 0);
      }

      return rightTime - leftTime;
    })[0] || null;
}

function getLastRunningEvent(events = []) {
  return [...events]
    .filter((event) => ['start', 'resume'].includes(event?.type))
    .sort((left, right) => {
      const leftTime = new Date(left?.timestamp || 0).getTime();
      const rightTime = new Date(right?.timestamp || 0).getTime();

      if (leftTime === rightTime) {
        return Number(right?.id || 0) - Number(left?.id || 0);
      }

      return rightTime - leftTime;
    })[0] || null;
}

function mapExecution(execution) {
  if (!execution?.id) return null;

  const events = Array.isArray(execution?.events) ? execution.events : [];
  const latestLifecycleEvent = getLatestLifecycleEvent(events);
  const lastRunningEvent = getLastRunningEvent(events);

  return {
    id: Number(execution.id),
    taskId: Number(execution?.task_id || 0),
    checkpointId:
      execution?.checkpoint_id === null || execution?.checkpoint_id === undefined
        ? null
        : Number(execution?.checkpoint_id || 0),
    durationSeconds: Number(execution?.duration_seconds || 0),
    startedAt: execution?.started_at || null,
    endedAt: execution?.ended_at || null,
    wasCompleted: Boolean(execution?.was_completed),
    latestLifecycleType: latestLifecycleEvent?.type || null,
    latestLifecycleAt: latestLifecycleEvent?.timestamp || null,
    lastRunningAt: lastRunningEvent?.timestamp || execution?.started_at || null
  };
}

export async function load({ cookies, fetch, url }) {
  const token = cookies.get('taskslayer_token');
  const taskId = Number(url.searchParams.get('taskId'));

  if (!token) {
    return {
      task: null,
      execution: null,
      loadError: 'No authenticated session found.'
    };
  }

  if (!Number.isInteger(taskId) || taskId <= 0) {
    return {
      task: null,
      execution: null,
      loadError: 'Select a quest before entering a boss battle.'
    };
  }

  try {
    const [taskResponse, executionResponse] = await Promise.all([
      fetchBackend(fetch, `/tasks/${taskId}`, {
        method: 'GET',
        headers: {
          Authorization: `Bearer ${token}`
        }
      }),
      fetchBackend(fetch, `/tasks/${taskId}/executions/current`, {
        method: 'GET',
        headers: {
          Authorization: `Bearer ${token}`
        }
      })
    ]);

    const [taskPayload, executionPayload] = await Promise.all([
      taskResponse.json().catch(() => ({})),
      executionResponse.json().catch(() => ({}))
    ]);

    if (!taskResponse.ok) {
      return {
        task: null,
        execution: null,
        loadError: taskPayload?.error || 'Could not load this quest.'
      };
    }

    if (!executionResponse.ok) {
      return {
        task: null,
        execution: null,
        loadError: executionPayload?.error || 'Could not load this quest session.'
      };
    }

    return {
      task: mapTask(taskPayload?.data || taskPayload),
      execution: mapExecution(executionPayload?.data || null),
      loadError: null
    };
  } catch {
    return {
      task: null,
      execution: null,
      loadError: 'Backend connection failed while loading this quest.'
    };
  }
}