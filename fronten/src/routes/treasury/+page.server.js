import { fail } from '@sveltejs/kit';
import { fetchBackend } from '$lib/server/api';

function extractList(payload) {
  const raw = payload?.data;

  if (Array.isArray(raw)) return raw;
  if (Array.isArray(raw?.data)) return raw.data;

  return [];
}

function extractBalance(payload) {
  const raw = payload?.data;
  return Number(raw?.points_balance || 0);
}

function mapReward(reward) {
  return {
    id: Number(reward?.id || 0),
    title: String(reward?.name || 'Unknown reward'),
    cost: Number(reward?.cost_points || 0),
    rewardType: String(reward?.reward_type || 'time'),
    durationMinutes: reward?.duration_minutes === null ? null : Number(reward?.duration_minutes || 0)
  };
}

function mapRedemption(redemption) {
  const rewardName = redemption?.reward?.name || 'Reward';
  const redeemedAt = redemption?.redeemed_at || null;

  return {
    id: Number(redemption?.id || 0),
    rewardName,
    pointsSpent: Number(redemption?.points_spent || 0),
    redeemedAt
  };
}

async function loadRewards(fetch, token) {
  const response = await fetchBackend(fetch, '/rewards', {
    method: 'GET',
    headers: {
      Authorization: `Bearer ${token}`
    }
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(payload?.error || 'Could not load rewards.');
  }

  return extractList(payload).map(mapReward).filter((reward) => reward.id > 0);
}

async function loadPointsBalance(fetch, token) {
  const response = await fetchBackend(fetch, '/points/balance', {
    method: 'GET',
    headers: {
      Authorization: `Bearer ${token}`
    }
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(payload?.error || 'Could not load points balance.');
  }

  return extractBalance(payload);
}

async function loadRedemptions(fetch, token) {
  const response = await fetchBackend(fetch, '/reward-redemptions/me', {
    method: 'GET',
    headers: {
      Authorization: `Bearer ${token}`
    }
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(payload?.error || 'Could not load redemption history.');
  }

  return extractList(payload).map(mapRedemption).filter((entry) => entry.id > 0);
}

export async function load({ cookies, fetch }) {
  const token = cookies.get('taskslayer_token');

  if (!token) {
    return {
      rewards: [],
      pointsBalance: 0,
      redemptions: [],
      loadError: 'No authenticated session found.'
    };
  }

  try {
    const [rewards, pointsBalance, redemptions] = await Promise.all([
      loadRewards(fetch, token),
      loadPointsBalance(fetch, token),
      loadRedemptions(fetch, token)
    ]);

    return {
      rewards,
      pointsBalance,
      redemptions,
      loadError: null
    };
  } catch (error) {
    return {
      rewards: [],
      pointsBalance: 0,
      redemptions: [],
      loadError: error?.message || 'Could not load treasury data.'
    };
  }
}

export const actions = {
  redeem: async ({ request, cookies, fetch }) => {
    const token = cookies.get('taskslayer_token');
    const formData = await request.formData();
    const rewardId = Number(formData.get('rewardId'));

    if (!token) {
      return fail(401, { message: 'Session expired. Please login again.' });
    }

    if (!Number.isInteger(rewardId) || rewardId <= 0) {
      return fail(400, { message: 'Invalid reward id.' });
    }

    try {
      const response = await fetchBackend(fetch, `/rewards/${rewardId}/redeem`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`
        }
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        return fail(response.status, {
          message: payload?.error || 'Could not redeem this reward.'
        });
      }

      const pointsBalance = Number(payload?.data?.points_balance || 0);
      const rewardName = payload?.data?.redemption?.reward?.name || 'reward';

      return {
        message: `Reward redeemed: ${rewardName}.`,
        pointsBalance
      };
    } catch {
      return fail(503, {
        message: 'Backend connection failed while redeeming reward.'
      });
    }
  }
};
