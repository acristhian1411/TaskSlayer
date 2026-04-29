import { env } from '$env/dynamic/private';
import { fail } from '@sveltejs/kit';

const LLM_COOKIE_NAME = 'taskslayer_llm_config';

function normalizeUrl(url) {
  const value = String(url || '').trim();
  return value.endsWith('/') ? value.slice(0, -1) : value;
}

function defaultLlmConfig() {
  return {
    provider: 'lmstudio',
    baseUrl: normalizeUrl(env.LMSTUDIO_BASE_URL || 'http://127.0.0.1:1234/v1'),
    model: String(env.LMSTUDIO_MODEL || 'local-model'),
    apiKey: String(env.LMSTUDIO_API_KEY || '')
  };
}

function parseStoredConfig(rawValue) {
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

function mergeWithDefaults(stored) {
  const defaults = defaultLlmConfig();

  if (!stored) {
    return defaults;
  }

  return {
    provider: stored.provider || defaults.provider,
    baseUrl: stored.baseUrl || defaults.baseUrl,
    model: stored.model || defaults.model,
    apiKey: stored.apiKey || defaults.apiKey
  };
}

function validateConfig(config) {
  if (!['lmstudio', 'openai-compatible'].includes(config.provider)) {
    return 'Invalid provider selected.';
  }

  if (!config.baseUrl) {
    return 'Base URL is required.';
  }

  try {
    const parsed = new URL(config.baseUrl);
    if (!['http:', 'https:'].includes(parsed.protocol)) {
      return 'Base URL must use http or https.';
    }
  } catch {
    return 'Base URL must be a valid URL.';
  }

  if (!config.model) {
    return 'Model is required.';
  }

  return null;
}

function llmCookieOptions() {
  return {
    path: '/',
    httpOnly: true,
    sameSite: 'lax',
    secure: false,
    maxAge: 60 * 60 * 24 * 30
  };
}

export function load({ cookies }) {
  const stored = parseStoredConfig(cookies.get(LLM_COOKIE_NAME));

  return {
    llmConfig: mergeWithDefaults(stored),
    llmDefaults: defaultLlmConfig()
  };
}

export const actions = {
  saveLlm: async ({ request, cookies }) => {
    const formData = await request.formData();

    const config = {
      provider: String(formData.get('provider') || 'lmstudio').trim(),
      baseUrl: normalizeUrl(formData.get('base_url') || ''),
      model: String(formData.get('model') || '').trim(),
      apiKey: String(formData.get('api_key') || '')
    };

    const validationError = validateConfig(config);

    if (validationError) {
      return fail(400, {
        message: validationError,
        llmConfig: config
      });
    }

    cookies.set(LLM_COOKIE_NAME, JSON.stringify(config), llmCookieOptions());

    return {
      message: 'LLM settings saved successfully.',
      llmConfig: config
    };
  },

  resetLlm: async ({ cookies }) => {
    cookies.delete(LLM_COOKIE_NAME, { path: '/' });

    return {
      message: 'LLM settings restored to LM Studio defaults from .env.',
      llmConfig: defaultLlmConfig()
    };
  }
};
