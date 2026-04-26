import { fail } from '@sveltejs/kit';
import { fetchBackend } from '$lib/server/api';

function normalizeTasks(payload) {
  const raw = payload?.data;

  if (Array.isArray(raw)) return raw;
  if (Array.isArray(raw?.data)) return raw.data;

  return [];
}

function mapTask(task) {
  return {
    id: Number(task?.id || 0),
    title: task?.title_rpg || task?.title_original || 'Unknown Quest',
    detail: task?.description || 'No quest details available.',
    difficultyLevel: Number(task?.difficulty_level || 1),
    rewardPoints: Number(task?.reward_points || 0),
    status: String(task?.status || 'pending'),
    createdAt: task?.created_at || null
  };
}

function buildKpis(tasks) {
  const active = tasks.filter((task) => task.status !== 'completed').length;
  const completed = tasks.filter((task) => task.status === 'completed').length;
  const potentialReward = tasks
    .filter((task) => task.status !== 'completed')
    .reduce((sum, task) => sum + task.rewardPoints, 0);

  return { active, completed, potentialReward };
}

export async function load({ cookies, fetch }) {
  const token = cookies.get('taskslayer_token');

  if (!token) {
    return {
      tasks: [],
      kpis: { active: 0, completed: 0, potentialReward: 0 },
      loadError: 'No authenticated session found.'
    };
  }

  try {
    const response = await fetchBackend(fetch, '/tasks', {
      method: 'GET',
      headers: {
        Authorization: `Bearer ${token}`
      }
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      return {
        tasks: [],
        kpis: { active: 0, completed: 0, potentialReward: 0 },
        loadError: payload?.error || 'Could not load quests.'
      };
    }

    const tasks = normalizeTasks(payload).map(mapTask).filter((task) => task.id > 0);

    return {
      tasks,
      kpis: buildKpis(tasks),
      loadError: null
    };
  } catch {
    return {
      tasks: [],
      kpis: { active: 0, completed: 0, potentialReward: 0 },
      loadError: 'Backend connection failed while loading quests.'
    };
  }
}

export const actions = {
  complete: async ({ request, cookies, fetch }) => {
    const token = cookies.get('taskslayer_token');
    const formData = await request.formData();
    const taskId = Number(formData.get('taskId'));

    if (!token) {
      return fail(401, { message: 'Session expired. Please login again.' });
    }

    if (!Number.isInteger(taskId) || taskId <= 0) {
      return fail(400, { message: 'Invalid quest id.' });
    }

    try {
      const response = await fetchBackend(fetch, `/tasks/${taskId}/complete`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`
        }
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        return fail(response.status, {
          message: payload?.error || 'Could not complete this quest.'
        });
      }

      return {
        message: 'Quest completed successfully.',
        completedTaskId: taskId
      };
    } catch {
      return fail(503, {
        message: 'Backend connection failed while completing the quest.'
      });
    }
  },

  uncomplete: async ({ request, cookies, fetch }) => {
    const token = cookies.get('taskslayer_token');
    const formData = await request.formData();
    const taskId = Number(formData.get('taskId'));

    if (!token) {
      return fail(401, { message: 'Session expired. Please login again.' });
    }

    if (!Number.isInteger(taskId) || taskId <= 0) {
      return fail(400, { message: 'Invalid quest id.' });
    }

    try {
      const response = await fetchBackend(fetch, `/tasks/${taskId}/uncomplete`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`
        }
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        return fail(response.status, {
          message: payload?.error || 'Could not revert this quest.'
        });
      }

      return {
        message: 'Quest moved back to active.',
        uncompletedTaskId: taskId
      };
    } catch {
      return fail(503, {
        message: 'Backend connection failed while reverting the quest.'
      });
    }
  }
};
