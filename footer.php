        </div> <!-- /.container -->
    </div> <!-- /.main -->
</div> <!-- /.layout -->

<?php if (isset($_SESSION['user_id']) && defined('TELEGRAM_SUPPORT') && TELEGRAM_SUPPORT): ?>
<a href="<?= htmlspecialchars(TELEGRAM_SUPPORT) ?>" target="_blank" rel="noopener noreferrer"
   class="floating-telegram"
   title="Chat on Telegram">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
        <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>
    </svg>
</a>
<?php endif; ?>

<div style="text-align: center; padding: 20px; color: #64748b; font-size: 12px;">
    &copy; <?= date("Y") ?> <?= SITE_NAME ?>. All rights reserved.
    <?php if (defined('TELEGRAM_SUPPORT') && TELEGRAM_SUPPORT): ?>
        · <a href="<?= htmlspecialchars(TELEGRAM_SUPPORT) ?>" target="_blank" rel="noopener noreferrer" style="color: #0088cc; text-decoration: none;">Telegram</a>
    <?php endif; ?>
    <?php if (defined('TELEGRAM_CHANNEL') && TELEGRAM_CHANNEL): ?>
        · <a href="<?= htmlspecialchars(TELEGRAM_CHANNEL) ?>" target="_blank" rel="noopener noreferrer" style="color: #0088cc; text-decoration: none;">Announcements</a>
    <?php endif; ?>
</div>

<style>
/* Prevent background scroll when sidebar is open */
body.sidebar-open {
    overflow: hidden;
    position: fixed;
    width: 100%;
    height: 100%;
}

/* Floating Telegram button */
.floating-telegram {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 56px;
    height: 56px;
    background: #0088cc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(0, 136, 204, 0.4);
    z-index: 997;
    transition: transform 0.2s, bottom 0.3s;
}

.floating-telegram:hover {
    transform: scale(1.1);
}

/* On small phones, keep it fully on-screen */
@media (max-width: 500px) {
    .floating-telegram {
        right: 16px;
        bottom: 16px;
        width: 52px;
        height: 52px;
    }
}

/* When sidebar is open, hide the Telegram button so it doesn't overlap */
body.sidebar-open .floating-telegram {
    opacity: 0;
    pointer-events: none;
}
</style>

<script>
// ============ Sidebar toggle ============
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar || !overlay) return;

    const isOpen = sidebar.classList.toggle('open');
    overlay.classList.toggle('show', isOpen);

    document.body.classList.toggle('sidebar-open', isOpen);
}

// ============ User dropdown ============
function toggleDropdown() {
    const dd = document.getElementById('userDropdown');
    if (!dd) return;
    dd.classList.toggle('show');
    document.getElementById('notifDropdown')?.classList.remove('show');
}

// ============ Notification dropdown ============
function toggleNotif() {
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;

    const wasOpen = dd.classList.contains('show');
    document.getElementById('userDropdown')?.classList.remove('show');

    if (wasOpen) {
        dd.classList.remove('show');
    } else {
        dd.classList.add('show');
        loadNotifications();
    }
}

function loadNotifications() {
    const listEl = document.getElementById('notifList');
    const dotEl = document.getElementById('notifDot');
    const countEl = document.getElementById('notifCount');
    if (!listEl) return;

    fetch('notifications.php', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                listEl.innerHTML = '<div class="notif-empty"><span class="empty-icon">⚠️</span>' + data.error + '</div>';
                return;
            }

            if (data.unread_count > 0) {
                dotEl.style.display = 'block';
                countEl.style.display = 'inline-block';
                countEl.textContent = data.unread_count;
            } else {
                dotEl.style.display = 'none';
                countEl.style.display = 'none';
            }

            if (!data.notifications || data.notifications.length === 0) {
                listEl.innerHTML = '<div class="notif-empty"><span class="empty-icon">📭</span>No notifications yet</div>';
                return;
            }

            listEl.innerHTML = data.notifications.map(n => `
                <a href="${n.link}" class="notif-item">
                    <div class="notif-icon">${n.icon}</div>
                    <div class="notif-body">
                        <div class="notif-text">${escapeHtml(n.text)}</div>
                        <div class="notif-time">${escapeHtml(n.ago)}</div>
                    </div>
                </a>
            `).join('');

            setTimeout(() => {
                fetch('mark_read.php', { credentials: 'same-origin' });
                dotEl.style.display = 'none';
                countEl.style.display = 'none';
            }, 1500);
        })
        .catch(() => {
            listEl.innerHTML = '<div class="notif-empty"><span class="empty-icon">⚠️</span>Failed to load</div>';
        });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ============ Close dropdowns when clicking outside ============
document.addEventListener('click', function(e) {
    const userMenu = document.getElementById('userDropdown');
    if (userMenu && !e.target.closest('.user-menu')) {
        userMenu.classList.remove('show');
    }

    const notifMenu = document.getElementById('notifDropdown');
    if (notifMenu && !e.target.closest('.notif-menu')) {
        notifMenu.classList.remove('show');
    }
});

// ============ Preload unread count ============
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('notifBtn')) {
        fetch('notifications.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.unread_count > 0) {
                    document.getElementById('notifDot').style.display = 'block';
                }
            })
            .catch(() => {});
    }
});

// ============ Auto-detect timezone ============
<?php if (isset($_SESSION['user_id'])): ?>
(function() {
    try {
        const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const currentTz = <?= json_encode($_SESSION['timezone'] ?? 'UTC') ?>;

        if (browserTz && browserTz !== currentTz) {
            fetch('set_timezone.php?tz=' + encodeURIComponent(browserTz), {
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) console.log('Timezone synced:', data.timezone);
            })
            .catch(() => {});
        }
    } catch (e) { /* silent */ }
})();
<?php endif; ?>
</script>
</body>
</html>