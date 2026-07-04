/**
 * OIManka - Frontend JavaScript
 *
 * Handles: AJAX interactions, chart rendering, gacha animations,
 * auto-refresh, stock search, and UI enhancements.
 */

(function () {
    'use strict';

    // ─── Auto-dismiss flash messages ───
    document.querySelectorAll('.flash-message').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.3s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 5000);
    });

    // ─── Clickable table rows ───
    document.querySelectorAll('.stock-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // Don't navigate if clicking a button or link
            if (e.target.closest('button, a, form, input')) return;
            const href = row.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    });

    // ─── Trade form - live preview ───
    document.querySelectorAll('.trade-form').forEach(function (form) {
        const qtyInput = form.querySelector('input[name="quantity"]');
        const previewEl = form.querySelector('.preview-amount');
        if (!qtyInput || !previewEl) return;

        const pricePerShare = parseFloat(previewEl.getAttribute('data-price')) || 0;
        const feePct = 0.01; // 1% fee

        function updatePreview() {
            const qty = parseInt(qtyInput.value) || 0;
            const subtotal = qty * pricePerShare;
            const fee = subtotal * feePct;
            const total = subtotal + fee;
            previewEl.textContent = total.toFixed(2);
        }

        qtyInput.addEventListener('input', updatePreview);
        qtyInput.addEventListener('change', updatePreview);
    });

    // ─── Market auto-refresh counter ───
    let refreshCountdown = null;
    function startMarketRefresh() {
        const marketPage = document.querySelector('.page-market');
        if (!marketPage) return;

        const countdownEl = document.createElement('span');
        countdownEl.className = 'refresh-countdown';
        countdownEl.style.cssText = 'font-size:12px;color:var(--text-muted);margin-left:12px;';

        const headerActions = document.querySelector('.page-header');
        if (headerActions) headerActions.appendChild(countdownEl);

        let seconds = 60;
        function tick() {
            seconds--;
            if (seconds <= 0) {
                countdownEl.textContent = '刷新中...';
                window.location.reload();
                return;
            }
            countdownEl.textContent = seconds + 's 后自动刷新';
            refreshCountdown = setTimeout(tick, 1000);
        }
        tick();
    }
    startMarketRefresh();

    // ─── Stock search ───
    const searchInput = document.getElementById('stock-search');
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const query = this.value.trim();
            if (query.length < 2) return;

            searchTimer = setTimeout(function () {
                const rows = document.querySelectorAll('.stock-row');
                const lowerQuery = query.toLowerCase();

                rows.forEach(function (row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(lowerQuery) ? '' : 'none';
                });
            }, 250);
        });
    }

    // ─── Admin sync button ───
    const syncBtn = document.getElementById('btn-sync');
    if (syncBtn) {
        syncBtn.addEventListener('click', function () {
            this.disabled = true;
            this.textContent = '同步中...';
            window.location.href = '/admin/sync';
        });
    }

    // ─── Price ticker animation ───
    function animatePriceChanges() {
        document.querySelectorAll('.td-change, .price-change').forEach(function (el) {
            const isPositive = el.textContent.trim().startsWith('+');
            el.style.animation = 'none';
            el.offsetHeight; // Trigger reflow
            el.style.animation = isPositive
                ? 'flashGreen 1s ease'
                : 'flashRed 1s ease';
        });
    }

    // Inject keyframes for flash animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes flashGreen {
            0% { background: rgba(74, 222, 128, 0.3); }
            100% { background: transparent; }
        }
        @keyframes flashRed {
            0% { background: rgba(248, 113, 113, 0.3); }
            100% { background: transparent; }
        }
    `;
    document.head.appendChild(style);

    animatePriceChanges();

    // ─── Mobile nav toggle ───
    const navToggle = document.getElementById('nav-toggle');
    if (navToggle) {
        navToggle.addEventListener('click', function () {
            document.querySelector('.site-nav').classList.toggle('open');
        });
    }

    // ─── Add keyboard shortcut for gacha ───
    document.addEventListener('keydown', function (e) {
        if (e.key === 'g' && e.ctrlKey) {
            e.preventDefault();
            const gachaPage = document.querySelector('.page-gacha');
            if (gachaPage) {
                document.getElementById('btnSingle')?.click();
            } else {
                window.location.href = '/gacha';
            }
        }
    });

    // ─── Init ───
    console.log('%c🎰 ' + '%cOIManka%c v1.0%c 已就绪',
        'font-size:20px;',
        'font-weight:bold;color:#ff6b9d;',
        'color:#a0a0c0;',
        'color:#666;'
    );
    console.log('%c股票市场 + 扭蛋抽卡 | PHP + SQLite | Adapter Pattern',
        'color:#666;font-size:11px;');

})();
