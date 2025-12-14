// notifications.js: fetch unread count and populate header badge
document.addEventListener('DOMContentLoaded', function () {
    const badge = document.getElementById('notifBadge');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifButton = document.getElementById('notifMenuButton');
    const notifMenu = document.querySelector('ul[aria-labelledby="notifMenuButton"]');

    // Determine app root (prefix before /admin/ or /employee/) so API works everywhere
    function getAppRoot() {
        const p = window.location.pathname;
        const idxA = p.indexOf('/admin/');
        if (idxA !== -1) return p.slice(0, idxA);
        const idxE = p.indexOf('/employee/');
        if (idxE !== -1) return p.slice(0, idxE);
        // Fallback to dirname of current path (site root)
        const parts = p.split('/').filter(Boolean);
        if (parts.length > 0) return '/' + parts[0];
        return '';
    }
    const APP_ROOT = getAppRoot();

    async function fetchCount() {
        try {
            const res = await fetch(`${APP_ROOT}/api/notifications.php?action=count`, { credentials: 'same-origin' });
            const j = await res.json();
            if (j.success) {
                const c = j.count || 0;
                if (badge) {
                    badge.textContent = c > 0 ? c : '';
                    badge.style.display = c > 0 ? 'inline-block' : 'none';
                }
            }
        } catch (e) {
            console.error('notif count error', e);
        }
    }

    async function fetchList() {
        try {
            const res = await fetch(`${APP_ROOT}/api/notifications.php?action=list&limit=5`, { credentials: 'same-origin' });
            const j = await res.json();
            if (j.success && Array.isArray(j.rows)) {
                if (notifDropdown) {
                    notifDropdown.innerHTML = '';
                        j.rows.forEach(r => {
                            const item = document.createElement('a');
                            let link = r.link || '#';
                            if (link === 'admin/reports.php') {
                                link = 'admin/manage_reasons.php?status=pending';
                            }
                            // Normalize relative links to absolute within /dtr-system
                            if (link !== '#') {
                                if (!link.startsWith('/')) {
                                    link = APP_ROOT + '/' + link.replace(/^\/+/, '');
                                }
                            }
                            item.href = link;
                            item.className = 'dropdown-item' + (r.is_read == 0 ? ' fw-bold' : '');
                            item.dataset.notificationId = r.notification_id || r.notificationId || '';

                            // Friendly formatter for known notification types
                            function formatNotification(row) {
                                let payload = row.payload;
                                if (typeof payload === 'string') {
                                    try { payload = JSON.parse(payload); } catch (e) { /* leave as string */ }
                                }
                                const type = row.type || '';
                                const entryTypeMap = {
                                    am_in: 'AM Clock In',
                                    am_out: 'AM Clock Out',
                                    pm_in: 'PM Clock In',
                                    pm_out: 'PM Clock Out'
                                };

                                // Helper: format a time value if ISO-like
                                function fmtTime(val) {
                                    if (!val) return '—';
                                    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(val)) {
                                        try {
                                            const dt = new Date(val);
                                            return dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                        } catch (e) { return val; }
                                    }
                                    return val;
                                }

                                // If payload failed to parse or isn't object, show a simple fallback
                                if (!payload || typeof payload !== 'object') {
                                    const raw = (typeof row.payload === 'string') ? row.payload : JSON.stringify(row.payload || {});
                                    return type ? `${type}: ${raw}` : raw;
                                }

                                // reason_submitted variants
                                if (type === 'reason_submitted') {
                                    const et = entryTypeMap[payload.entry_type] || payload.entry_type || 'Entry';
                                    const rec = payload.record_id ? `Record #${payload.record_id}` : 'a record';
                                    // Some submissions include old/new values, others only basic info
                                    if (payload.old_value || payload.new_value) {
                                        const oldVal = fmtTime(payload.old_value) || 'previous value';
                                        const newVal = fmtTime(payload.new_value) || 'new value';
                                        return `Edit request: ${rec} (${et}) from ${oldVal} to ${newVal}. Awaiting review.`;
                                    }
                                    return `Reason submitted for ${rec} (${et}). Awaiting review.`;
                                }

                                if (type === 'reason_approved') {
                                    const et = entryTypeMap[payload.entry_type] || payload.entry_type || 'Entry';
                                    const rec = payload.record_id ? `Record #${payload.record_id}` : 'your record';
                                    return `Good news! Your edit for ${rec} (${et}) was approved.`;
                                }

                                if (type === 'reason_rejected') {
                                    const rec = payload.record_id ? `Record #${payload.record_id}` : 'your record';
                                    return `Update request for ${rec} was not approved.`;
                                }

                                if (type === 'account_created') {
                                    const name = payload.name || `Employee ${payload.employee_id || ''}`.trim();
                                    return `New account created: ${name}.`;
                                }

                                // Default fallback keeps technical type but softens wording
                                return `Notification: ${type}`;
                            }

                            const friendly = formatNotification(r);
                            item.textContent = friendly || r.created_at || 'Notification';
                            item.addEventListener('click', async function (ev) {
                                // mark as read then follow link
                                ev.preventDefault();
                                const nid = this.dataset.notificationId;
                                if (nid) {
                                    try {
                                        const form = new FormData();
                                        form.append('notification_id', nid);
                                        await fetch(`${APP_ROOT}/api/notifications.php?action=mark_read`, { method: 'POST', body: form, credentials: 'same-origin' });
                                    } catch (e) { console.error('mark read failed', e); }
                                }
                                // navigate
                                if (this.href && this.href !== '#') window.location = this.href;
                            });
                            notifDropdown.appendChild(item);
                        });
                }
            }
        } catch (e) {
            console.error('notif list error', e);
        }
    }

    // initial fetch
    fetchCount();
    fetchList();

    // poll every 30s for both count and list (was only count every 60s)
    setInterval(() => { fetchCount(); fetchList(); }, 30000);

    // Optional manual refresh if user leaves dropdown open >30s
    if (notifButton) {
        notifButton.addEventListener('shown.bs.dropdown', () => {
            fetchList();
        });

        if (window.bootstrap && window.bootstrap.Dropdown) {
            window.bootstrap.Dropdown.getOrCreateInstance(notifButton);
        } else {
            notifButton.addEventListener('click', (ev) => {
                if (!notifMenu) return;
                ev.preventDefault();
                const willShow = !notifMenu.classList.contains('show');
                notifMenu.classList.toggle('show');
                notifButton.setAttribute('aria-expanded', notifMenu.classList.contains('show') ? 'true' : 'false');
                if (willShow) fetchList();
            });

            document.addEventListener('click', (ev) => {
                if (!notifMenu) return;
                if (notifMenu.contains(ev.target) || notifButton.contains(ev.target)) return;
                if (notifMenu.classList.contains('show')) {
                    notifMenu.classList.remove('show');
                    notifButton.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }
});
