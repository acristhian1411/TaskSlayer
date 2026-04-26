import { browser } from '$app/environment';

export const THEME_STORAGE_KEY = 'taskslayer.theme';

export function normalizeThemePreference(value) {
  return ['system', 'dark', 'light'].includes(value) ? value : 'system';
}

export function getSystemTheme() {
  if (!browser) return 'dark';

  return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
}

export function getStoredThemePreference() {
  if (!browser) return 'system';

  return normalizeThemePreference(window.localStorage.getItem(THEME_STORAGE_KEY) || 'system');
}

export function resolveTheme(preference) {
  const normalized = normalizeThemePreference(preference);
  return normalized === 'system' ? getSystemTheme() : normalized;
}

export function applyThemePreference(preference) {
  const normalized = normalizeThemePreference(preference);
  const theme = resolveTheme(normalized);

  if (!browser) {
    return { preference: normalized, theme };
  }

  document.documentElement.dataset.theme = theme;
  document.documentElement.dataset.themePreference = normalized;
  window.localStorage.setItem(THEME_STORAGE_KEY, normalized);

  return { preference: normalized, theme };
}

export function syncStoredThemePreference() {
  return applyThemePreference(getStoredThemePreference());
}