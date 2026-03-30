// Payroll API client — connects to Azure Functions backend at /api/payroll
const API_BASE = '/api/payroll';

async function api(action, options = {}) {
  const { method = 'GET', body = null, params = {} } = options;
  const url = new URL(API_BASE, window.location.origin);
  url.searchParams.set('action', action);
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

  const fetchOpts = { method, headers: {} };
  if (body) {
    fetchOpts.headers['Content-Type'] = 'application/json';
    fetchOpts.body = JSON.stringify(body);
  }

  try {
    const res = await fetch(url.toString(), fetchOpts);
    if (!res.ok) {
      const err = await res.json().catch(() => ({ error: 'Network error' }));
      console.error('[PayrollAPI]', action, err);
      return null;
    }
    return await res.json();
  } catch (e) {
    console.error('[PayrollAPI]', action, e);
    return null;
  }
}

// ─── Exported API methods ────────────────────────────────────────────

// Load all payroll data for a week (single request)
export async function loadWeekData(weekStart) {
  return api('load_week', { params: { week_start: weekStart } });
}

// Settings
export async function getSettings() {
  return api('get_settings');
}

export async function saveSettings(rates, updatedBy) {
  return api('save_settings', {
    method: 'POST',
    body: { ...rates, updatedBy },
  });
}

// User rate overrides
export async function getUserRates() {
  return api('get_user_rates');
}

export async function saveUserRate(userId, commPct, snrPct, hourlyRate) {
  return api('save_user_rate', {
    method: 'POST',
    body: { userId, commPct, snrPct, hourlyRate },
  });
}

export async function deleteUserRate(userId) {
  return api('delete_user_rate', { method: 'POST', body: { userId } });
}

// Admin hours
export async function saveAdminHours(userId, hours, weekStart) {
  return api('save_admin_hours', {
    method: 'POST',
    body: { userId, hours, weekStart },
  });
}

// Adjustments
export async function addAdjustment(entryId, userId, type, description, amount, createdBy) {
  return api('add_adjustment', {
    method: 'POST',
    body: { entryId, userId, type, description, amount, createdBy },
  });
}

export async function removeAdjustment(id) {
  return api('remove_adjustment', { method: 'POST', body: { id } });
}

// Deal overrides
export async function saveDealOverride(userId, dealId, weekStart, type, value) {
  return api('save_deal_override', {
    method: 'POST',
    body: { userId, dealId, weekStart, type, value },
  });
}

export async function undoDealOverride(userId, dealId, weekStart, type) {
  return api('undo_deal_override', {
    method: 'POST',
    body: { userId, dealId, weekStart, type },
  });
}

// Manual deals
export async function addManualDeal(userId, weekStart, name, amount, date, vd, createdBy) {
  return api('add_manual_deal', {
    method: 'POST',
    body: { userId, weekStart, name, amount, date, vd, createdBy },
  });
}

export async function updateManualDeal(id, name, amount, date, vd) {
  return api('update_manual_deal', {
    method: 'POST',
    body: { id, name, amount, date, vd },
  });
}

export async function removeManualDeal(id) {
  return api('remove_manual_deal', { method: 'POST', body: { id } });
}

// Notes
export async function saveNote(userId, weekStart, note, createdBy) {
  return api('save_note', {
    method: 'POST',
    body: { userId, weekStart, note, createdBy },
  });
}

// Payroll entries
export async function saveEntry(data) {
  return api('save_entry', { method: 'POST', body: data });
}

export async function deleteEntry(id) {
  return api('delete_entry', { method: 'POST', body: { id } });
}

// Send paysheet
export async function sendPaysheet(userId, userName, userRole, weekStart, weekLabel, finalPay, sentBy, snapshot) {
  return api('send_paysheet', {
    method: 'POST',
    body: { userId, userName, userRole, weekStart, weekLabel, finalPay, sentBy, snapshot },
  });
}

// Sent sheets
export async function getSentSheets(weekStart) {
  return api('get_sent_sheets', { params: weekStart ? { week_start: weekStart } : {} });
}

// History
export async function getHistory(userId, limit = 50) {
  const params = { limit: String(limit) };
  if (userId) params.user_id = userId;
  return api('get_history', { params });
}

// Export CSV URL
export function getExportCSVUrl(weekStart) {
  const url = new URL(API_BASE, window.location.origin);
  url.searchParams.set('action', 'export_csv');
  url.searchParams.set('week_start', weekStart);
  return url.toString();
}
