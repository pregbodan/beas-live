/* BEAS — Main JS */

// ── Finger Btn Styles ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Style finger buttons
    document.querySelectorAll('.finger-btn').forEach(btn => {
        btn.style.cssText = `
            display: flex; flex-direction: column; align-items: center;
            gap: 4px; padding: 10px 6px; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: 8px;
            color: var(--text-secondary); cursor: pointer;
            font-size: .68rem; transition: all .15s;
            font-family: var(--font-body);
        `;
        if (btn.classList.contains('captured')) {
            btn.style.borderColor = 'var(--signal)';
            btn.style.color = 'var(--signal)';
        }
    });

    document.querySelectorAll('.finger-select-btn').forEach(btn => {
        applyFingerSelectStyle(btn);
    });

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity .4s';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });
});

function applyFingerSelectStyle(btn) {
    btn.style.cssText = `
        padding: 6px 12px; border-radius: 20px; font-size: .78rem;
        border: 1px solid var(--border); background: transparent;
        color: var(--text-secondary); cursor: pointer;
        font-family: var(--font-body); transition: all .15s;
    `;
    if (btn.classList.contains('active')) {
        btn.style.background    = 'var(--electric-glow)';
        btn.style.borderColor   = 'var(--electric)';
        btn.style.color         = '#6B9FFF';
    }
}

// ── Finger select re-style on click ──────────────────────
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('finger-select-btn')) {
        const parent = e.target.closest('[style]') || document.body;
        const btns   = e.target.parentElement.querySelectorAll('.finger-select-btn');
        btns.forEach(b => {
            b.classList.remove('active');
            applyFingerSelectStyle(b);
        });
        e.target.classList.add('active');
        applyFingerSelectStyle(e.target);
    }
});

// ── Table search (client side for small tables) ───────────
function clientSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('keyup', function () {
        const term = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
}

// ── Toast notification ────────────────────────────────────
function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    const colors = {
        success: { bg: 'rgba(0,200,150,.15)', border: 'rgba(0,200,150,.3)', color: 'var(--signal)' },
        error:   { bg: 'rgba(255,75,85,.15)', border: 'rgba(255,75,85,.3)', color: 'var(--danger)' },
        info:    { bg: 'var(--electric-glow)', border: 'rgba(30,111,255,.3)', color: '#6B9FFF' },
    };
    const c = colors[type] || colors.info;
    toast.style.cssText = `
        position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        background: ${c.bg}; border: 1px solid ${c.border}; color: ${c.color};
        padding: 12px 18px; border-radius: 8px; font-size: .84rem;
        font-family: var(--font-body); box-shadow: 0 8px 30px rgba(0,0,0,.5);
        max-width: 320px; animation: slideUp .25s ease;
    `;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 3500);
}

// ── Confirm delete helper ─────────────────────────────────
function confirmAction(msg, form) {
    if (confirm(msg)) form.submit();
}

// Add animation keyframe
const style = document.createElement('style');
style.textContent = '@keyframes slideUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }';
document.head.appendChild(style);
