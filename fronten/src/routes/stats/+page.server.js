import { fetchBackend } from '$lib/server/api';

function extractData(payload) {
  return payload?.data ?? null;
}

async function getJson(fetch, token, path) {
  const response = await fetchBackend(fetch, path, {
    method: 'GET',
    headers: {
      Authorization: `Bearer ${token}`
    }
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(payload?.error || `Could not load ${path}.`);
  }

  return extractData(payload);
}

function mapSummary(raw) {
  return {
    totalSessions: Number(raw?.total_sessions || 0),
    completedSessions: Number(raw?.completed_sessions || 0),
    completionRate: Number(raw?.completion_rate || 0),
    totalDurationSeconds: Number(raw?.total_duration_seconds || 0)
  };
}

function mapTimeByTask(raw) {
  if (!Array.isArray(raw)) return [];

  return raw.map((item) => ({
    taskId: Number(item?.task_id || 0),
    title: item?.title_rpg || item?.title_original || 'Unknown quest',
    sessions: Number(item?.sessions || 0),
    totalDurationSeconds: Number(item?.total_duration_seconds || 0)
  }));
}

function mapDaily(raw) {
  if (!Array.isArray(raw)) return [];

  return raw.map((item) => ({
    day: String(item?.day || ''),
    sessions: Number(item?.sessions || 0),
    totalDurationSeconds: Number(item?.total_duration_seconds || 0)
  }));
}

function mapPoints(raw) {
  return {
    earnedPoints: Number(raw?.earned_points || 0),
    spentPoints: Number(raw?.spent_points || 0),
    balance: Number(raw?.balance || 0),
    entriesCount: Number(raw?.entries_count || 0)
  };
}

export async function load({ cookies, fetch }) {
  const token = cookies.get('taskslayer_token');

  if (!token) {
    return {
      summary: mapSummary(null),
      timeByTask: [],
      dailyProductivity: [],
      points: mapPoints(null),
      loadError: 'No authenticated session found.'
    };
  }

  try {
    const [summaryRaw, timeByTaskRaw, dailyRaw, pointsRaw] = await Promise.all([
      getJson(fetch, token, '/task-executions/metrics/summary'),
      getJson(fetch, token, '/task-executions/metrics/time-by-task'),
      getJson(fetch, token, '/task-executions/metrics/daily-productivity'),
      getJson(fetch, token, '/points/summary')
    ]);

    return {
      summary: mapSummary(summaryRaw),
      timeByTask: mapTimeByTask(timeByTaskRaw),
      dailyProductivity: mapDaily(dailyRaw),
      points: mapPoints(pointsRaw),
      loadError: null
    };
  } catch (error) {
    return {
      summary: mapSummary(null),
      timeByTask: [],
      dailyProductivity: [],
      points: mapPoints(null),
      loadError: error?.message || 'Could not load stats.'
    };
  }
}
