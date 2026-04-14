'use strict';
/* IT Manager Pro v2 — Complete JS — All bugs fixed */

const APP_URL = (document.querySelector('meta[name="app-url"]')?.content || '').replace(/\/+$/, '');
const IS_RTL  = document.documentElement.getAttribute('dir') === 'rtl';
const LANG    = document.documentElement.getAttribute('lang') || 'en';

/* ── CSRF ────────────────────────────────────────────────── */
function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }

/* ── API wrapper ─────────────────────────────────────────── */
async function api(url, data = null, method = 'POST') {
  const full = url.startsWith('http') ? url : APP_URL + url;
  const opts = { method, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' } };
  if (data) {
    if (data instanceof FormData) { data.set('csrf_token', csrf()); opts.body = data; }
    else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify({ ...data, csrf_token: csrf() }); }
  }
  try {
    const res = await fetch(full, opts);
    if (res.status === 419 || res.status === 403) {
      Toast.show(LANG === 'ar' ? 'انتهت الجلسة. يتم التحديث...' : 'Session expired. Refreshing...', 'warning');
      setTimeout(() => location.reload(), 1500);
      return { success: false };
    }
    const ct = res.headers.get('content-type') || '';
    if (ct.includes('application/json')) return await res.json();
    return { success: res.ok };
  } catch (e) {
    console.error('API error:', e);
    return { success: false, message: LANG === 'ar' ? 'خطأ في الاتصال' : 'Network error.' };
  }
}
window.api = api;

/* ── Toast ───────────────────────────────────────────────── */
const Toast = {
  _rack: null,
  init() {
    if (this._rack) return;
    this._rack = document.createElement('div');
    this._rack.id = 'toast-rack';
    document.body.appendChild(this._rack);
  },
  show(msg, type = 'success', ms = 4500) {
    this.init();
    const icons = { success: 'check-circle-fill', danger: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    const el = document.createElement('div');
    el.className = `toast-item ${type}`;
    el.innerHTML = `<i class="bi bi-${icons[type] || 'info-circle-fill'}"></i><span style="flex:1">${msg}</span><button class="toast-x" onclick="this.parentElement.remove()">×</button>`;
    this._rack.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 320); }, ms);
  }
};
window.Toast = Toast;

/* ── Confirm helper ──────────────────────────────────────── */
window.confirmDelete = function(url, msg) {
  const m = msg || (LANG === 'ar' ? 'هل أنت متأكد من حذف هذا السجل؟ لا يمكن التراجع.' : 'Are you sure you want to delete this? This cannot be undone.');
  if (!confirm(m)) return;
  window.location.href = url;
};

/* ── Sidebar ─────────────────────────────────────────────── */
function initSidebar() {
  const sb  = document.getElementById('appSidebar');
  const btn = document.getElementById('sidebarToggle');
  const bd  = document.getElementById('sidebarBackdrop');
  if (!sb || !btn) return;
  const mobile = () => window.innerWidth < 992;
  btn.addEventListener('click', () => {
    if (mobile()) { sb.classList.toggle('open'); bd?.classList.toggle('show'); }
  });
  bd?.addEventListener('click', () => { sb.classList.remove('open'); bd.classList.remove('show'); });
}

/* ── Theme toggle (persists to DB) ───────────────────────── */
function initTheme() {
  const btn = document.getElementById('themeToggle');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-bs-theme') === 'dark';
    const next   = isDark ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    const icon = btn.querySelector('i');
    if (icon) icon.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    await api('/actions/theme.php', { theme: next });
  });
}

/* ── Language switch ─────────────────────────────────────── */
window.switchLang = async function(lang) {
  const res = await api('/actions/lang.php', { lang });
  if (res.success !== false) location.reload();
};

/* ── Global search (FIXED) ───────────────────────────────── */
let _searchTimer = null;
function initGlobalSearch() {
  const input   = document.getElementById('globalSearch');
  const dropdown = document.getElementById('searchDropdown');
  if (!input || !dropdown) return;

  input.addEventListener('input', () => {
    clearTimeout(_searchTimer);
    const q = input.value.trim();
    if (q.length < 2) { dropdown.classList.remove('show'); dropdown.innerHTML = ''; return; }
    _searchTimer = setTimeout(async () => {
      const data = await api('/api/search.php', { q });
      renderSearchResults(data, dropdown, q);
    }, 250);
  });

  document.addEventListener('click', e => {
    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.remove('show');
    }
  });

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); input.focus(); input.select(); }
  });
}

function renderSearchResults(data, dropdown, q) {
  const cfg = {
    assets:    { icon: 'bi-laptop',       label: LANG==='ar'?'الأصول':'Assets',    url: '/pages/assets.php?id=' },
    users:     { icon: 'bi-person',       label: LANG==='ar'?'الموظفون':'Users',   url: '/pages/users.php?id=' },
    licenses:  { icon: 'bi-tags',         label: LANG==='ar'?'التراخيص':'Licenses', url: '/pages/licenses.php?id=' },
    documents: { icon: 'bi-file-earmark', label: LANG==='ar'?'المستندات':'Documents', url: '/pages/documents.php?id=' },
  };
  let html = '';
  let found = false;
  for (const [type, items] of Object.entries(data || {})) {
    if (!Array.isArray(items) || !items.length) continue;
    found = true;
    const c = cfg[type] || { icon: 'bi-circle', label: type, url: '#' };
    html += `<div class="search-group-title">${c.label}</div>`;
    items.forEach(item => {
      const hl = (item.name || '').replace(new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), m => `<mark style="background:rgba(59,130,246,.2);color:inherit;border-radius:2px;padding:0 2px;">${m}</mark>`);
      html += `<a href="${APP_URL}${c.url}${item.id}" class="search-item">
        <div class="search-item-icon"><i class="bi ${c.icon}"></i></div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${hl}</div>
          ${item.code ? `<div style="font-size:11px;color:#64748b;font-family:var(--font-mono)">${item.code}</div>` : ''}
        </div>
        ${item.status ? `<span style="font-size:9.5px;padding:2px 6px;border-radius:4px;background:rgba(100,116,139,.2);color:#94a3b8;">${item.status}</span>` : ''}
      </a>`;
    });
  }
  if (!found) html = `<div class="search-no-result"><i class="bi bi-search mb-1 d-block fs-5"></i>${LANG==='ar'?'لا توجد نتائج':'No results found'}</div>`;
  dropdown.innerHTML = html;
  dropdown.classList.add('show');
}

/* ── Notifications ───────────────────────────────────────── */
function initNotifications() {
  document.querySelectorAll('.notif-row[data-id]').forEach(el => {
    el.addEventListener('click', () => {
      api('/actions/notifications.php', { id: el.dataset.id, action: 'read' });
      el.classList.remove('unread');
    });
  });
  document.querySelector('.notif-mark-all')?.addEventListener('click', async e => {
    e.preventDefault();
    const res = await api('/actions/notifications.php', { action: 'read_all' });
    if (res.success) {
      document.querySelector('.tb-badge')?.remove();
      document.querySelectorAll('.notif-row').forEach(el => el.classList.remove('unread'));
    }
  });
}

/* ── Password toggle ─────────────────────────────────────── */
function initPasswordToggle() {
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-toggle-pw]');
    if (!btn) return;
    const t = document.querySelector(btn.dataset.togglePw);
    if (!t) return;
    const show = t.type === 'password';
    t.type = show ? 'text' : 'password';
    const i = btn.querySelector('i');
    if (i) i.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
}

/* ── Copy to clipboard ───────────────────────────────────── */
window.copyToClipboard = function(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    Toast.show(LANG==='ar'?'تم النسخ!':'Copied!', 'success', 1800);
    if (btn) {
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check2 text-success"></i>';
      setTimeout(() => { btn.innerHTML = orig; }, 2000);
    }
  }).catch(() => Toast.show('Copy failed', 'danger'));
};

/* ── AJAX forms (fixed for multipart + JSON) ─────────────── */
function initAjaxForms() {
  document.addEventListener('submit', async e => {
    const form = e.target;
    if (!form.hasAttribute('data-ajax')) return;
    e.preventDefault();
    const btn = form.querySelector('[type=submit]');
    const origHTML = btn?.innerHTML;
    const saveTxt = LANG==='ar' ? '<span class="spinner-border spinner-border-sm me-1"></span>جاري...' : '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    if (btn) { btn.disabled = true; btn.innerHTML = saveTxt; }

    const fd = new FormData(form);
    const action = form.action || form.dataset.action || '';

    const res = await api(action, fd);

    if (res.success) {
      Toast.show(res.message || (LANG==='ar'?'تم الحفظ بنجاح':'Saved successfully!'), 'success');
      const modal = form.closest('.modal');
      if (modal) bootstrap.Modal.getInstance(modal)?.hide();
      if (res.redirect) setTimeout(() => location.href = APP_URL + res.redirect, 700);
      else if (res.reload !== false) setTimeout(() => location.reload(), 700);
    } else {
      Toast.show(res.message || (LANG==='ar'?'حدث خطأ':'An error occurred.'), 'danger');
    }
    if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }
  });
}

/* ── Vault reveal (completely rewritten, bug-free) ───────── */
window.revealVaultPassword = async function(id, btn) {
  const row    = btn.closest('[data-vault-id]');
  const passEl = row?.querySelector('.vault-pass-display');
  if (!passEl) return;

  if (passEl.dataset.revealed === '1') {
    passEl.textContent = '••••••••••••';
    passEl.dataset.revealed = '0';
    const i = btn.querySelector('i');
    if (i) i.className = 'bi bi-eye';
    return;
  }

  btn.disabled = true;
  const orig = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  try {
    let res = await api('/actions/vault_reveal.php', { id: String(id) });

    if (res.need_master) {
      const mp = prompt(LANG==='ar' ? 'أدخل كلمة المرور الرئيسية للخزانة:' : 'Enter your vault master password:');
      if (!mp) { btn.innerHTML = orig; btn.disabled = false; return; }
      res = await api('/actions/vault_reveal.php', { id: String(id), master_password: mp });
    }

    if (res.success) {
      passEl.textContent = res.password;
      passEl.dataset.revealed = '1';
      const i = btn.querySelector('i');
      if (i) i.className = 'bi bi-eye-slash';
      else btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
      Toast.show(res.message || (LANG==='ar'?'خطأ في فك التشفير':'Decryption failed'), 'danger');
      btn.innerHTML = orig;
    }
  } catch(e) {
    Toast.show('Error', 'danger');
    btn.innerHTML = orig;
  } finally {
    btn.disabled = false;
  }
};

window.copyVaultPassword = async function(id, btn) {
  let res = await api('/actions/vault_reveal.php', { id: String(id) });
  if (res.need_master) {
    const mp = prompt(LANG==='ar' ? 'أدخل كلمة المرور الرئيسية:' : 'Enter vault master password:');
    if (!mp) return;
    res = await api('/actions/vault_reveal.php', { id: String(id), master_password: mp });
  }
  if (res.success) window.copyToClipboard(res.password, btn);
  else Toast.show(res.message || 'Error', 'danger');
};

/* ── Bulk select ─────────────────────────────────────────── */
function initBulkSelect() {
  const all = document.getElementById('selectAll');
  if (!all) return;
  const updateBar = () => {
    const n = document.querySelectorAll('.row-cb:checked').length;
    const bar = document.getElementById('bulkBar');
    const cnt = document.getElementById('bulkCount');
    if (bar) bar.classList.toggle('show', n > 0);
    if (cnt) cnt.textContent = n;
  };
  all.addEventListener('change', () => {
    document.querySelectorAll('.row-cb').forEach(cb => {
      cb.checked = all.checked;
      cb.closest('tr')?.classList.toggle('row-selected', all.checked);
    });
    updateBar();
  });
  document.addEventListener('change', e => {
    if (!e.target.classList.contains('row-cb')) return;
    e.target.closest('tr')?.classList.toggle('row-selected', e.target.checked);
    updateBar();
  });
}

window.getSelectedIds = () => [...document.querySelectorAll('.row-cb:checked')].map(cb => cb.value);

window.bulkAction = async function(action, extra = {}) {
  const ids = window.getSelectedIds();
  if (!ids.length) { Toast.show(LANG==='ar'?'اختر سجلات أولاً':'Select records first', 'warning'); return; }
  const res = await api('/actions/bulk.php', { action, ids, ...extra });
  if (res.success) { Toast.show(res.message || 'Done!', 'success'); setTimeout(() => location.reload(), 700); }
  else Toast.show(res.message || 'Error', 'danger');
};

/* ── Password strength ───────────────────────────────────── */
window.checkPasswordStrength = function(pw, targetId) {
  const el = document.getElementById(targetId);
  if (!el) return;
  if (!pw) { el.innerHTML = ''; return; }
  let s = 0;
  if (pw.length >= 8) s++; if (pw.length >= 14) s++;
  if (/[A-Z]/.test(pw)) s++; if (/[0-9]/.test(pw)) s++;
  if (/[^A-Za-z0-9]/.test(pw)) s++;
  const labels = ['', 'Weak','Fair','Good','Strong','Excellent'];
  const colors = ['', 'danger','warning','info','success','success'];
  el.innerHTML = `<div class="d-flex align-items-center gap-2 mt-1">
    <div class="progress flex-grow-1" style="height:4px"><div class="progress-bar bg-${colors[s]}" style="width:${s*20}%"></div></div>
    <small class="text-${colors[s]}" style="font-size:11px;white-space:nowrap;">${labels[s]}</small>
  </div>`;
};

window.generatePassword = function(fieldId) {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
  const arr = new Uint8Array(20);
  crypto.getRandomValues(arr);
  const pw = Array.from(arr, b => chars[b % chars.length]).join('');
  const field = document.getElementById(fieldId);
  if (field) { field.type = 'text'; field.value = pw; field.dispatchEvent(new Event('input')); }
};

/* ── Barcode/QR ──────────────────────────────────────────── */
window.generateBarcode = function(elId, value) {
  const el = document.getElementById(elId);
  if (!el || !value || typeof JsBarcode === 'undefined') return;
  try { JsBarcode(el, value, { format:'CODE128', width:2, height:55, displayValue:true, fontSize:11, margin:6, background:'#ffffff', lineColor:'#000000' }); }
  catch(e) { console.warn('Barcode:', e); }
};

window.generateQR = function(canvasId, value) {
  const el = document.getElementById(canvasId);
  if (!el || !value || typeof QRCode === 'undefined') return;
  el.innerHTML = '';
  QRCode.toCanvas(el, value, { width: 120, margin: 2 }, err => { if(err) console.warn('QR:', err); });
};

/* ── CSV export ──────────────────────────────────────────── */
window.exportTable = function(tableId, filename = 'export') {
  const t = document.getElementById(tableId);
  if (!t) return;
  const rows = [...t.querySelectorAll('tr')].map(r =>
    [...r.querySelectorAll('th,td')].map(c => '"' + c.innerText.replace(/"/g,'""').replace(/\n/g,' ') + '"').join(',')
  );
  const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const a = Object.assign(document.createElement('a'), {
    href: URL.createObjectURL(blob),
    download: `${filename}_${new Date().toISOString().slice(0,10)}.csv`
  });
  a.click(); URL.revokeObjectURL(a.href);
};

/* ── Auto-dismiss alerts ─────────────────────────────────── */
function initAlerts() {
  document.querySelectorAll('.alert-dismissible').forEach(el => {
    setTimeout(() => { try { bootstrap.Alert.getOrCreateInstance(el)?.close(); } catch(e){} }, 5000);
  });
}

/* ── Tooltips ────────────────────────────────────────────── */
function initTooltips() {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    try { new bootstrap.Tooltip(el, { trigger: 'hover' }); } catch(e){}
  });
}

/* ── Auto-render barcodes ────────────────────────────────── */
function initBarcodes() {
  document.querySelectorAll('[data-barcode]').forEach(el => {
    if (el.id) window.generateBarcode(el.id, el.dataset.barcode);
  });
  document.querySelectorAll('[data-qr]').forEach(el => {
    if (el.id) window.generateQR(el.id, el.dataset.qr);
  });
}

/* ── Boot ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  Toast.init();
  initSidebar();
  initTheme();
  initGlobalSearch();
  initNotifications();
  initPasswordToggle();
  initAjaxForms();
  initBulkSelect();
  initTooltips();
  initBarcodes();
  initAlerts();
});
