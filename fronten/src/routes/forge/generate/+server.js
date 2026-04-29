import { env } from '$env/dynamic/private';
import { json } from '@sveltejs/kit';

const LLM_COOKIE_NAME = 'taskslayer_llm_config';

function normalizeUrl(url) {
  return String(url || '').endsWith('/') ? String(url).slice(0, -1) : String(url);
}

function parseStoredLlmConfig(rawValue) {
  if (!rawValue) return null;

  try {
    const parsed = JSON.parse(rawValue);

    if (!parsed || typeof parsed !== 'object') {
      return null;
    }

    return {
      provider: String(parsed.provider || '').trim() || 'lmstudio',
      baseUrl: normalizeUrl(parsed.baseUrl || ''),
      model: String(parsed.model || '').trim(),
      apiKey: String(parsed.apiKey || '')
    };
  } catch {
    return null;
  }
}

function resolveLlmConfig(storedConfig) {
  const defaults = {
    provider: 'lmstudio',
    baseUrl: normalizeUrl(env.LMSTUDIO_BASE_URL || 'http://127.0.0.1:1234/v1'),
    model: String(env.LMSTUDIO_MODEL || 'local-model'),
    apiKey: String(env.LMSTUDIO_API_KEY || '')
  };

  if (!storedConfig) {
    return defaults;
  }

  return {
    provider: storedConfig.provider || defaults.provider,
    baseUrl: storedConfig.baseUrl || defaults.baseUrl,
    model: storedConfig.model || defaults.model,
    apiKey: storedConfig.apiKey || defaults.apiKey
  };
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

function buildMessages(task, checkpoints = []) {
  const checkpointTitles = Array.isArray(checkpoints)
    ? checkpoints
        .map((cp) => String(cp?.title || cp || '').trim())
        .filter(Boolean)
    : [];

  let systemContent =
    `Actua como un disenador de tareas RPG para productividad tecnica.
Responde en espanol y devuelve SOLO JSON valido con claves:
boss_name, narrative, difficulty_level, reward_points, tags.
REGLAS:
- La tarea del usuario es el eje principal. TODO debe derivar de ella.
- Puedes usar un tono ligero de fantasia (metaforas, "boss", etc.), pero SIN crear mundos o historias irrelevantes.
- El boss_name debe ser una version metaforica de la tarea, manteniendo palabras clave reconocibles.
- narrative debe describir el reto tecnico con un toque RPG, pero enfocado en lo que realmente hay que hacer.
- Maximo 2-3 lineas de narrativa.
- Incluye al menos una palabra exacta de la tarea en boss_name o narrative.
RESTRICCIONES:
- difficulty_level: entero entre 1 y 4.
- reward_points: uno de [10, 30, 60, 120].
- tags: arreglo de 1 a 4 palabras directamente relacionadas con la tarea.`;

  if (checkpointTitles.length > 0) {
    systemContent +=
      '\n\nEl usuario ha definido estos checkpoints (subtareas) para esta raid:\n' +
      checkpointTitles.map((title, i) => `${i + 1}. ${title}`).join('\n') +
      '\n\nGenera una narrativa que sea coherente con estos checkpoints.';
  }

  return [
    {
      role: 'system',
      content: systemContent
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

export async function POST({ request, fetch, cookies }) {
  const payload = await request.json().catch(() => ({}));
  const task = String(payload?.task || '').trim();
  const checkpoints = Array.isArray(payload?.checkpoints) ? payload.checkpoints : [];

  if (!task) {
    return json({ ok: false, error: 'Task is required.' }, { status: 400 });
  }

  const storedConfig = parseStoredLlmConfig(cookies.get(LLM_COOKIE_NAME));
  const llmConfig = resolveLlmConfig(storedConfig);

  const baseCandidates =
    llmConfig.provider === 'lmstudio'
      ? buildLmStudioBaseCandidates(llmConfig.baseUrl)
      : [normalizeUrl(llmConfig.baseUrl)].filter(Boolean);

  const headers = {
    'Content-Type': 'application/json'
  };

  if (llmConfig.apiKey) {
    headers.Authorization = `Bearer ${llmConfig.apiKey}`;
  }

  let lastConnectionError = null;

  for (const base of baseCandidates) {
    try {
      const response = await fetch(`${base}/chat/completions`, {
        method: 'POST',
        headers,
        body: JSON.stringify({
          model: llmConfig.model,
          temperature: 0.2,
          max_tokens: 220,
          response_format: { type: 'text' },
          messages: buildMessages(task, checkpoints)
        })
      });

      const lmPayload = await response.json().catch(() => ({}));

      if (!response.ok) {
        const reason =
          lmPayload?.error?.message ||
          lmPayload?.error ||
          `LLM provider returned status ${response.status}`;
        return json({ ok: false, error: String(reason) }, { status: response.status });
      }

      const content = String(lmPayload?.choices?.[0]?.message?.content || '').trim();
      const parsed = extractJsonObject(content);

      if (!parsed) {
        return json(
          {
            ok: false,
            error: 'LLM provider response did not contain valid JSON.'
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
      error: `LLM connection failed: ${lastConnectionError || 'unknown error'}`
    },
    { status: 503 }
  );
}
