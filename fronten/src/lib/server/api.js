import { env } from '$env/dynamic/private';

function normalizeUrl(url) {
  return url.endsWith('/') ? url.slice(0, -1) : url;
}

export function getApiBaseUrl() {
  // Prefer internal URL for server-side requests (container-safe), then public URL.
  return normalizeUrl(env.API_URL || env.PUBLIC_API_URL || 'http://localhost:8080/api');
}

export async function fetchBackend(fetchFn, path, options = {}) {
  const cleanPath = path.startsWith('/') ? path : `/${path}`;
  const url = `${getApiBaseUrl()}${cleanPath}`;

  try {
    return await fetchFn(url, {
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.headers || {})
      }
    });
  } catch (error) {
    throw new Error(`Backend request failed: ${url} :: ${error?.message || 'unknown error'}`);
  }
}

export async function fetchMe(fetchFn, token) {
  if (!token) return null;

  try {
    const response = await fetchBackend(fetchFn, '/auth/me', {
      method: 'GET',
      headers: {
        Authorization: `Bearer ${token}`
      }
    });

    if (!response.ok) return null;

    const payload = await response.json().catch(() => ({}));
    return payload?.data || null;
  } catch {
    return null;
  }
}

export async function logoutFromBackend(fetchFn, token) {
  if (!token) return;

  try {
    await fetchBackend(fetchFn, '/auth/logout', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`
      }
    });
  } catch {
    // Ignore backend logout failures and clear local cookie anyway.
  }
}
