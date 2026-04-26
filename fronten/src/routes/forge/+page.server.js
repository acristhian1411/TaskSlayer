import { fail } from '@sveltejs/kit';
import { fetchBackend } from '$lib/server/api';

function toInt(value, fallback) {
  const parsed = Number(value);
  return Number.isInteger(parsed) ? parsed : fallback;
}

function buildDescriptionWithSource(originalTask, narrative) {
  const source = String(originalTask || '').trim();
  const details = String(narrative || '').trim();

  if (!source && !details) {
    return null;
  }

  if (!source) {
    return details || null;
  }

  if (!details) {
    return `Original objective: ${source}`;
  }

  return [`Original objective: ${source}`, '', `Battle plan: ${details}`].join('\n');
}

export const actions = {
  create: async ({ request, cookies, fetch }) => {
    const token = cookies.get('taskslayer_token');
    const formData = await request.formData();

    const titleOriginal = String(formData.get('title_original') || '').trim();
    const titleRpg = String(formData.get('title_rpg') || '').trim();
    const description = String(formData.get('description') || '').trim();
    const difficultyLevel = toInt(formData.get('difficulty_level'), 1);
    const rewardPoints = toInt(formData.get('reward_points'), 10);
    const normalizedDescription = buildDescriptionWithSource(titleOriginal, description);

    if (!token) {
      return fail(401, {
        message: 'Session expired. Please login again.'
      });
    }

    if (!titleOriginal || !titleRpg) {
      return fail(400, {
        message: 'Original task and forged title are required.'
      });
    }

    if (difficultyLevel < 1 || difficultyLevel > 4) {
      return fail(400, {
        message: 'Difficulty level must be between 1 and 4.'
      });
    }

    if (rewardPoints < 0) {
      return fail(400, {
        message: 'Reward points cannot be negative.'
      });
    }

    try {
      const response = await fetchBackend(fetch, '/tasks', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          title_original: titleOriginal,
          title_rpg: titleRpg,
          description: normalizedDescription,
          difficulty_level: difficultyLevel,
          reward_points: rewardPoints,
          status: 'pending'
        })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        return fail(response.status, {
          message: payload?.error || 'Could not create quest.'
        });
      }

      return {
        message: 'Quest forged and saved successfully.'
      };
    } catch {
      return fail(503, {
        message: 'Backend connection failed while creating quest.'
      });
    }
  }
};
