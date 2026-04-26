import { env } from '$env/dynamic/private';
import { json } from '@sveltejs/kit';

function normalizeUrl(url) {
  return String(url || '').endsWith('/') ? String(url).slice(0, -1) : String(url);
}

function buildLmStudioBaseCandidates(baseUrl) {
  const normalized = normalizeUrl(baseUrl);
  const candidates = [];

  const push = (value) => {
    if (value && !candidates.includes(value)) {
      candidates.push(value);
    }
  };

  push(normalized);

  try {
    const parsed = new URL(normalized);
    const isLoopback = ['127.0.0.1', 'localhost', '::1'].includes(parsed.hostname);

    if (isLoopback) {
      const dockerHost = new URL(parsed.toString());
      dockerHost.hostname = 'host.docker.internal';
      push(normalizeUrl(dockerHost.toString()));

      const defaultBridge = new URL(parsed.toString());
      defaultBridge.hostname = '172.17.0.1';
      push(normalizeUrl(defaultBridge.toString()));
    }
  } catch {
    return candidates;
  }

  return candidates;
}

function clampDifficulty(level) {
  return Math.min(4, Math.max(1, Number(level) || 1));
}

function rewardForLevel(level) {
  const map = { 1: 10, 2: 30, 3: 60, 4: 120 };
  return map[clampDifficulty(level)] || 10;
}

function buildDescriptionWithSource(originalTask, narrative) {
  const source = String(originalTask || '').trim();
  const details = String(narrative || '').trim();

  if (!source && !details) {
    return 'Take on this mission with precision and claim your reward.';
  }

  if (!source) {
    return details;
  }

  if (!details) {
    return `Original objective: ${source}`;
  }

  return [`Original objective: ${source}`, '', `Battle plan: ${details}`].join('\n');
}

function extractJsonObject(text) {
  if (!text) return null;

  try {
    return JSON.parse(text);
  } catch {
    const match = String(text).match(/\{[\s\S]*\}/);
    if (!match) return null;

    try {
      return JSON.parse(match[0]);
    } catch {
      return null;
    }
  }
}

function normalizeForgePayload(raw, originalTask) {
  const difficulty = clampDifficulty(raw?.difficulty_level);
  const reward = [10, 30, 60, 120].includes(Number(raw?.reward_points))
    ? Number(raw.reward_points)
    : rewardForLevel(difficulty);

  const tags = Array.isArray(raw?.tags)
    ? raw.tags
        .map((tag) => String(tag).trim())
        .filter(Boolean)
        .slice(0, 4)
    : [];

  const fallbackTitle = `The ${originalTask.split(' ').slice(0, 4).join(' ').toUpperCase()}`;

  return {
    titleRpg: String(raw?.boss_name || '').trim() || fallbackTitle,
    description: buildDescriptionWithSource(originalTask, String(raw?.narrative || '').trim()),
    difficultyLevel: difficulty,
    rewardPoints: reward,
    tags: tags.length > 0 ? tags : ['Focus']
  };
}

function buildMessages(task) {
  return [
    {
      role: 'system',
      content:
        'Actua como un disenador de tareas RPG. Responde en espanol y devuelve solo JSON valido con claves: boss_name, narrative, difficulty_level, reward_points, tags.'
    },
    {
      role: 'user',
      content: [
        'difficulty_level debe ser un entero entre 1 y 4.',
        'reward_points debe ser uno de [10, 30, 60, 120].',
        'tags debe ser un arreglo de 1 a 4 palabras cortas.',
        `Tarea: ${task}`
      ].join('\n')
    }
  ];
}

export async function POST({ request, fetch }) {
  const payload = await request.json().catch(() => ({}));
  const task = String(payload?.task || '').trim();

  if (!task) {
    return json({ ok: false, error: 'Task is required.' }, { status: 400 });
  }

  const lmStudioBase = normalizeUrl(env.LMSTUDIO_BASE_URL || 'http://127.0.0.1:1234/v1');
  const baseCandidates = buildLmStudioBaseCandidates(lmStudioBase);
  const lmStudioModel = env.LMSTUDIO_MODEL || 'local-model';
  const lmStudioApiKey = env.LMSTUDIO_API_KEY || '';

  const headers = {
    'Content-Type': 'application/json'
  };

  if (lmStudioApiKey) {
    headers.Authorization = `Bearer ${lmStudioApiKey}`;
  }

  let lastConnectionError = null;

  for (const base of baseCandidates) {
    try {
      const response = await fetch(`${base}/chat/completions`, {
        method: 'POST',
        headers,
        body: JSON.stringify({
          model: lmStudioModel,
          temperature: 0.2,
          max_tokens: 220,
          response_format: { type: 'text' },
          messages: buildMessages(task)
        })
      });

      const lmPayload = await response.json().catch(() => ({}));

      if (!response.ok) {
        const reason =
          lmPayload?.error?.message ||
          lmPayload?.error ||
          `LM Studio returned status ${response.status}`;
        return json({ ok: false, error: String(reason) }, { status: response.status });
      }

      const content = String(lmPayload?.choices?.[0]?.message?.content || '').trim();
      const parsed = extractJsonObject(content);

      if (!parsed) {
        return json(
          {
            ok: false,
            error: 'LM Studio response did not contain valid JSON.'
          },
          { status: 502 }
        );
      }

      return json({
        ok: true,
        data: normalizeForgePayload(parsed, task)
      });
    } catch (error) {
      lastConnectionError = `${base}: ${error?.message || 'unknown error'}`;
    }
  }

  return json(
    {
      ok: false,
      error: `LM Studio connection failed: ${lastConnectionError || 'unknown error'}`
    },
    { status: 503 }
  );
}
