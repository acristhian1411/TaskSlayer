import { redirect } from '@sveltejs/kit';
import { fetchMe } from '$lib/server/api';

const PUBLIC_ROUTES = ['/login'];

function isPublicRoute(pathname) {
  return PUBLIC_ROUTES.some((route) => pathname === route || pathname.startsWith(`${route}/`));
}

export async function load({ cookies, fetch, url }) {
  const token = cookies.get('taskslayer_token');
  const publicRoute = isPublicRoute(url.pathname);

  if (!token) {
    if (!publicRoute) {
      throw redirect(303, '/login');
    }

    return { user: null };
  }

  const user = await fetchMe(fetch, token);

  if (!user) {
    cookies.delete('taskslayer_token', { path: '/' });

    if (!publicRoute) {
      throw redirect(303, '/login');
    }

    return { user: null };
  }

  if (publicRoute) {
    throw redirect(303, '/');
  }

  return { user };
}
