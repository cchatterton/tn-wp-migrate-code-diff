(function () {
    'use strict';

    var results = document.getElementById('twmcd-options-results');
    var loadingCard = document.getElementById('twmcd-options-loading-card');
    var message = document.getElementById('twmcd-options-message');
    var refresh = document.getElementById('twmcd-options-refresh');
    if (!results) {
        return;
    }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function statusLabel(option) {
        var labels = TWMCD_OPTIONS_ADMIN.labels;
        if (option.ignored) { return labels.ignored; }
        return { different: labels.different, source_only: labels.sourceOnly, destination_only: labels.destinationOnly }[option.status] || option.status;
    }

    function updateCount(card) {
        card.querySelector('.twmcd-selected-count').textContent = '(' + card.querySelectorAll('input:checked').length + ' selected)';
    }

    function renderTable(table, index) {
        var id = 'twmcd-options-table-' + index;
        var rows = table.options.map(function (option) {
            return '<tr' + (option.ignored ? ' class="twmcd-ignored-row"' : '') + '><td><input type="checkbox" data-recommended="' + (option.default_selected ? '1' : '0') + '"'
                + (option.default_selected ? ' checked' : '') + (option.ignored ? ' disabled' : '') + ' aria-label="Select ' + escapeHtml(option.name) + '"></td>'
                + '<td><strong>' + escapeHtml(option.name) + '</strong></td><td><span class="twmcd-status twmcd-status-' + escapeHtml(option.status) + '">' + escapeHtml(statusLabel(option)) + '</span></td></tr>';
        }).join('');
        if (!rows) { rows = '<tr><td colspan="3">No option differences found.</td></tr>'; }

        return '<section class="twmcd-card twmcd-options-group"><div class="twmcd-group-heading"><h2><button type="button" class="button-link twmcd-accordion-toggle" aria-expanded="true" aria-controls="' + id + '"><span class="twmcd-accordion-icon" aria-hidden="true">&#9662;</span> '
            + escapeHtml(table.name) + ' <span class="twmcd-selected-count"></span></button></h2><div class="twmcd-group-toggles">'
            + '<button class="button-link" type="button" data-select="all">Select all</button> / <button class="button-link" type="button" data-select="none">Deselect all</button> / <button class="button-link" type="button" data-select="recommended">Recommended</button></div></div>'
            + '<div id="' + id + '" class="twmcd-table-scroll twmcd-accordion-content"><table class="widefat striped"><thead><tr><th class="check-column"></th><th>Option</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody></table></div></section>';
    }

    function render(data) {
        var differenceCount = data.tables.reduce(function (count, table) { return count + table.options.length; }, 0);
        var scope = data.scope_label ? '<br>' + escapeHtml(data.scope_label) : '';
        document.getElementById('twmcd-options-summary').innerHTML = '<strong>' + escapeHtml(data.source_url) + '</strong> &rarr; <strong>' + escapeHtml(data.destination_url) + '</strong>' + scope + '<br>' + differenceCount + ' option differences found. Transients are excluded.';
        document.getElementById('twmcd-options-groups').innerHTML = data.tables.map(renderTable).join('');
        Array.prototype.forEach.call(document.querySelectorAll('.twmcd-options-group'), updateCount);
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
            var card = select.closest('.twmcd-options-group');
            var mode = select.getAttribute('data-select');
            Array.prototype.forEach.call(card.querySelectorAll('input:not(:disabled)'), function (input) {
                input.checked = mode === 'all' || (mode === 'recommended' && input.getAttribute('data-recommended') === '1');
            });
            updateCount(card);
        }
    });
    results.addEventListener('change', function (event) {
        if (event.target.matches('input[type="checkbox"]')) { updateCount(event.target.closest('.twmcd-options-group')); }
    });

    function load() {
        if (!TWMCD_OPTIONS_ADMIN.contextToken) {
            message.innerHTML = '<div class="notice notice-error inline"><p>No live WP Migrate context was supplied. Return to Migrate and choose Compare Options.</p></div>';
            return;
        }
        refresh.disabled = true;
        results.hidden = true;
        loadingCard.hidden = false;
        message.innerHTML = '';
        document.getElementById('twmcd-options-loading').classList.add('is-active');
        var body = new URLSearchParams({ action: 'twmcd_compare_options', nonce: TWMCD_OPTIONS_ADMIN.nonce, context_token: TWMCD_OPTIONS_ADMIN.contextToken });
        fetch(TWMCD_OPTIONS_ADMIN.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
            .then(function (response) { return response.json(); })
            .then(function (response) {
                if (!response.success) { throw new Error(response.data && response.data.message ? response.data.message : TWMCD_OPTIONS_ADMIN.labels.comparisonFailed); }
                render(response.data);
            }).catch(function (error) {
                message.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(error.message) + '</p></div>';
                document.getElementById('twmcd-options-loading').classList.remove('is-active');
            }).then(function () { refresh.disabled = false; });
    }

    refresh.addEventListener('click', load);
    load();
}());
