import { env } from '$env/dynamic/private';
import { fail, redirect } from '@sveltejs/kit';
import { fetchBackend } from '$lib/server/api';

function cookieOptions() {
  return {
    path: '/',
    httpOnly: true,
    sameSite: 'lax',
    secure: env.NODE_ENV === 'production',
    maxAge: 60 * 60 * 24 * 7
  };
}

export const actions = {
  default: async ({ request, cookies, fetch }) => {
    const formData = await request.formData();
    const email = String(formData.get('email') || '').trim();
    const password = String(formData.get('password') || '');

    if (!email || !password) {
      return fail(400, {
        message: 'Email y password son obligatorios.',
        email
      });
    }

    let response;

    try {
      response = await fetchBackend(fetch, '/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ email, password })
      });
    } catch {
      return fail(503, {
        message: 'No se pudo conectar con el backend de autenticacion.',
        email
      });
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      return fail(response.status, {
        message: payload?.error || 'No se pudo iniciar sesion.',
        email
      });
    }

    const token = payload?.data?.token;

    if (!token) {
      return fail(500, {
        message: 'Respuesta de autenticacion invalida.',
        email
      });
    }

    cookies.set('taskslayer_token', token, cookieOptions());

    throw redirect(303, '/');
  }
};
