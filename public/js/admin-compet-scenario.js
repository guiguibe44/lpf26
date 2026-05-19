/**
 * Panneau scénario FR–DE : IDs localStorage, substitution CLI/cron, copie.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'lpf26_compet_scenario_ids_v1';
    var VARS = ['MATCH_ID', 'BUTEUR_FR_1', 'BUTEUR_DE_1', 'BUTEUR_FR_2', 'CRON_SECRET', 'BASE_URL'];

    function loadIds() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function saveIds(data) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
            /* ignore */
        }
    }

    function substitute(template, ids) {
        var out = template;
        VARS.forEach(function (key) {
            var val = ids[key] || '{' + key + '}';
            out = out.split('{' + key + '}').join(val);
        });
        return out;
    }

    function buildCronUrl(basePath, params, ids) {
        var base = (ids.BASE_URL || '').replace(/\/$/, '');
        var path = basePath.indexOf('/') === 0 ? basePath : '/' + basePath;
        var token = ids.CRON_SECRET || 'VOTRE_SECRET';
        var matchId = ids.MATCH_ID || 'MATCH_ID';
        var q = 'token=' + encodeURIComponent(token)
            + '&match_id=' + encodeURIComponent(matchId)
            + '&' + params;
        return base + path + '?' + q;
    }

    function flashCopy(btn) {
        btn.classList.add('admin-compet-scenario__copy--ok');
        setTimeout(function () {
            btn.classList.remove('admin-compet-scenario__copy--ok');
        }, 1200);
    }

    function copyText(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (btn) {
                    flashCopy(btn);
                }
            });
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        if (btn) {
            flashCopy(btn);
        }
    }

    function refreshCommands(root, ids) {
        root.querySelectorAll('[data-scenario-cli]').forEach(function (el) {
            el.textContent = substitute(el.textContent, ids);
        });

        var cronPath = root.getAttribute('data-cron-path') || '/cron/test-match-step';

        root.querySelectorAll('[data-scenario-cron]').forEach(function (el) {
            var params = el.getAttribute('data-cron-params') || '';
            el.textContent = buildCronUrl(cronPath, substitute(params, ids), ids);
        });

        root.querySelectorAll('[data-copy-text]').forEach(function (btn) {
            var tpl = btn.getAttribute('data-copy-text') || '';
            btn.setAttribute('data-copy-resolved', substitute(tpl, ids));
        });
    }

    function init() {
        var root = document.querySelector('[data-admin-compet-scenario]');
        if (!root) {
            return;
        }

        var ids = loadIds();

        root.querySelectorAll('[data-scenario-var]').forEach(function (input) {
            var key = input.getAttribute('data-scenario-var');
            if (key && ids[key]) {
                input.value = ids[key];
            }

            input.addEventListener('input', function () {
                ids[key] = input.value.trim();
                saveIds(ids);
                refreshCommands(root, ids);
            });
        });

        refreshCommands(root, ids);

        root.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-copy-text], [data-copy-cron]');
            if (!btn) {
                return;
            }

            if (btn.hasAttribute('data-copy-cron')) {
                var cronEl = btn.parentElement.querySelector('[data-scenario-cron]');
                if (cronEl) {
                    copyText(cronEl.textContent, btn);
                }
                return;
            }

            var text = btn.getAttribute('data-copy-resolved') || btn.getAttribute('data-copy-text') || '';
            copyText(substitute(text, ids), btn);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
