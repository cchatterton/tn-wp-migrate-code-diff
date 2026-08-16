(function () {
    'use strict';

    var results = document.getElementById('twmcd-database-results');
    var loadingCard = document.getElementById('twmcd-database-loading-card');
    var message = document.getElementById('twmcd-database-message');
    var refresh = document.getElementById('twmcd-database-refresh');
    if (!results) {
        return;
    }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function metric(value, suffix) {
        return value === null || typeof value === 'undefined' ? '—' : Number(value).toLocaleString() + (suffix || '');
    }

    function statusLabel(status) {
        var labels = TWMCD_DATABASE_ADMIN.labels;
        return { same: labels.same, different: labels.different, source_only: labels.sourceOnly, destination_only: labels.destinationOnly }[status] || status;
    }

    function updateCount(card) {
        var selected = card.querySelectorAll('input[type="checkbox"]:checked').length;
        card.querySelector('.twmcd-selected-count').textContent = '(' + selected + ' selected)';
    }

    function renderGroup(key, label, tables) {
        var id = 'twmcd-database-' + key;
        var rows = tables.map(function (table, index) {
            return '<tr><td><input type="checkbox" data-recommended="' + (table.default_selected ? '1' : '0') + '"'
                + (table.default_selected ? ' checked' : '') + ' aria-label="Select ' + escapeHtml(table.name) + '"></td>'
                + '<td><strong>' + escapeHtml(table.name) + '</strong></td>'
                + '<td><span class="twmcd-status twmcd-status-' + escapeHtml(table.status) + '">' + escapeHtml(statusLabel(table.status)) + '</span></td>'
                + '<td>' + metric(table.source_rows) + '</td><td>' + metric(table.destination_rows) + '</td>'
                + '<td>' + metric(table.source_size_kb, ' KB') + '</td><td>' + metric(table.destination_size_kb, ' KB') + '</td></tr>';
        }).join('');

        return '<section class="twmcd-card twmcd-report-group" data-group="' + key + '">'
            + '<div class="twmcd-group-heading"><h2><button type="button" class="button-link twmcd-accordion-toggle" aria-expanded="true" aria-controls="' + id + '">'
            + '<span class="twmcd-accordion-icon" aria-hidden="true">&#9662;</span> ' + escapeHtml(label) + ' <span class="twmcd-selected-count"></span></button></h2>'
            + '<div class="twmcd-group-toggles"><button class="button-link" type="button" data-select="all">Select all</button> / '
            + '<button class="button-link" type="button" data-select="none">Deselect all</button> / '
            + '<button class="button-link" type="button" data-select="recommended">Recommended</button></div></div>'
            + '<div id="' + id + '" class="twmcd-table-scroll twmcd-accordion-content"><table class="widefat striped"><thead><tr><th class="check-column"></th><th>Logical table</th><th>Status</th><th>Source rows</th><th>Destination rows</th><th>Source size</th><th>Destination size</th></tr></thead><tbody>'
            + rows + '</tbody></table></div></section>';
    }

    function render(data) {
        var nativeTables = data.groups.native || [];
        var customTables = data.groups.custom || [];
        var differences = nativeTables.concat(customTables).filter(function (table) { return table.status !== 'same'; }).length;
        var scope = data.scope_label ? '<br>' + escapeHtml(data.scope_label) : '';
        document.getElementById('twmcd-database-summary').innerHTML = '<strong>' + escapeHtml(data.source_url) + '</strong> &rarr; <strong>' + escapeHtml(data.destination_url) + '</strong>' + scope + '<br>' + differences + ' table differences found.';
        document.getElementById('twmcd-database-groups').innerHTML = renderGroup('native', 'WordPress tables', nativeTables) + renderGroup('custom', 'Custom tables', customTables);
        Array.prototype.forEach.call(document.querySelectorAll('.twmcd-report-group'), updateCount);
        loadingCard.hidden = true;
        results.hidden = false;
    }

    results.addEventListener('click', function (event) {
        var toggle = event.target.closest && event.target.closest('.twmcd-accordion-toggle');
        var select = event.target.closest && event.target.closest('[data-select]');
        if (toggle) {
            var content = document.getElementById(toggle.getAttribute('aria-controls'));
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            content.hidden = expanded;
        }
        if (select) {
            var card = select.closest('.twmcd-report-group');
            var mode = select.getAttribute('data-select');
            Array.prototype.forEach.call(card.querySelectorAll('input[type="checkbox"]'), function (input) {
                input.checked = mode === 'all' || (mode === 'recommended' && input.getAttribute('data-recommended') === '1');
            });
            updateCount(card);
        }
    });
    results.addEventListener('change', function (event) {
        if (event.target.matches('input[type="checkbox"]')) {
            updateCount(event.target.closest('.twmcd-report-group'));
        }
    });

    function load() {
        if (!TWMCD_DATABASE_ADMIN.contextToken) {
            message.innerHTML = '<div class="notice notice-error inline"><p>No live WP Migrate context was supplied. Return to Migrate and choose Compare Database.</p></div>';
            return;
        }
        refresh.disabled = true;
        results.hidden = true;
        loadingCard.hidden = false;
        message.innerHTML = '';
        document.getElementById('twmcd-database-loading').classList.add('is-active');
        var body = new URLSearchParams({ action: 'twmcd_compare_database', nonce: TWMCD_DATABASE_ADMIN.nonce, context_token: TWMCD_DATABASE_ADMIN.contextToken });
        fetch(TWMCD_DATABASE_ADMIN.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
            .then(function (response) { return response.json(); })
            .then(function (response) {
                if (!response.success) { throw new Error(response.data && response.data.message ? response.data.message : TWMCD_DATABASE_ADMIN.labels.comparisonFailed); }
                render(response.data);
            }).catch(function (error) {
                message.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(error.message) + '</p></div>';
                document.getElementById('twmcd-database-loading').classList.remove('is-active');
            }).then(function () { refresh.disabled = false; });
    }

    refresh.addEventListener('click', load);
    load();
}());
