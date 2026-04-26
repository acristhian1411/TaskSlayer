import { redirect } from '@sveltejs/kit';
import { logoutFromBackend } from '$lib/server/api';

export async function load({ cookies, fetch }) {
  const token = cookies.get('taskslayer_token');

  await logoutFromBackend(fetch, token);

  cookies.delete('taskslayer_token', { path: '/' });

  throw redirect(303, '/login');
}
