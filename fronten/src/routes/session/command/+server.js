import { json } from '@sveltejs/kit';
import { fetchBackend } from '$lib/server/api';

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
    durationSeconds: Number(execution?.duration_seconds || 0),
    startedAt: execution?.started_at || null,
    endedAt: execution?.ended_at || null,
    wasCompleted: Boolean(execution?.was_completed),
    latestLifecycleType: latestLifecycleEvent?.type || null,
    latestLifecycleAt: latestLifecycleEvent?.timestamp || null,
    lastRunningAt: lastRunningEvent?.timestamp || execution?.started_at || null
  };
}

function buildRequest(action, payload) {
  if (action === 'start') {
    return {
      path: `/tasks/${payload.taskId}/executions/start`,
      method: 'POST',
      body: payload.startedAt ? { started_at: payload.startedAt } : {}
    };
  }

  if (action === 'pause') {
    return {
      path: `/task-executions/${payload.executionId}/pause`,
      method: 'POST',
      body: {}
    };
  }

  if (action === 'resume') {
    return {
      path: `/task-executions/${payload.executionId}/resume`,
      method: 'POST',
      body: {}
    };
  }

  if (action === 'completeExecution') {
    return {
      path: `/task-executions/${payload.executionId}/complete`,
      method: 'POST',
      body: payload.endedAt ? { ended_at: payload.endedAt } : {}
    };
  }

  if (action === 'completeTask') {
    return {
      path: `/tasks/${payload.taskId}/complete`,
      method: 'POST',
      body: {}
    };
  }

  return null;
}

export async function POST({ cookies, fetch, request }) {
  const token = cookies.get('taskslayer_token');

  if (!token) {
    return json({ error: 'No authenticated session found.' }, { status: 401 });
  }

  const payload = await request.json().catch(() => ({}));
  const action = String(payload?.action || '');
  const backendRequest = buildRequest(action, payload);

  if (!backendRequest) {
    return json({ error: 'Invalid session command.' }, { status: 400 });
  }

  try {
    const response = await fetchBackend(fetch, backendRequest.path, {
      method: backendRequest.method,
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(backendRequest.body)
    });

    const responsePayload = await response.json().catch(() => ({}));

    if (!response.ok) {
      return json(
        { error: responsePayload?.error || 'Could not update quest session.' },
        { status: response.status }
      );
    }

    return json({
      data: mapExecution(responsePayload?.data || null),
      message: responsePayload?.message || null
    });
  } catch {
    return json({ error: 'Backend connection failed while updating the quest session.' }, { status: 503 });
  }
}