(function () {
    'use strict';

    var resultsElement = document.getElementById('twmcd-database-results');
    var loadingCard = document.getElementById('twmcd-database-loading-card');
    var messageElement = document.getElementById('twmcd-database-message');

    if (!resultsElement) {
        return;
    }

    resultsElement.addEventListener('click', function (event) {
        var toggle = event.target.closest ? event.target.closest('.twmcd-accordion-toggle') : null;
        if (!toggle) {
            return;
        }
        var content = document.getElementById(toggle.getAttribute('aria-controls'));
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (content) {
            content.hidden = expanded;
        }
    });

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function request() {
        var body = new URLSearchParams({
            action: 'twmcd_compare_database_images',
            nonce: TWMCD_DATABASE_ADMIN.nonce,
            context_token: TWMCD_DATABASE_ADMIN.contextToken
        });

        return fetch(TWMCD_DATABASE_ADMIN.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (response) {
            return response.json();
        });
    }

    function statusLabel(status) {
        var labels = {
            same: TWMCD_DATABASE_ADMIN.labels.same,
            different: TWMCD_DATABASE_ADMIN.labels.different,
            source_only: TWMCD_DATABASE_ADMIN.labels.sourceOnly,
            destination_only: TWMCD_DATABASE_ADMIN.labels.destinationOnly
        };
        return labels[status] || status;
    }

    function metric(value, suffix) {
        return value === null || typeof value === 'undefined' ? '—' : Number(value).toLocaleString() + (suffix || '');
    }

    function renderTables(tables) {
        var markup = '<table class="widefat striped"><thead><tr><th>Logical table</th><th>Status</th><th>Source rows</th><th>Destination rows</th><th>Source size</th><th>Destination size</th></tr></thead><tbody>';
        tables.forEach(function (table) {
            markup += '<tr><td><strong>' + escapeHtml(table.name) + '</strong></td>';
            markup += '<td><span class="twmcd-status twmcd-status-' + escapeHtml(table.status) + '">' + escapeHtml(statusLabel(table.status)) + '</span></td>';
            markup += '<td>' + metric(table.source_rows) + '</td><td>' + metric(table.destination_rows) + '</td>';
            markup += '<td>' + metric(table.source_size_kb, ' KB') + '</td><td>' + metric(table.destination_size_kb, ' KB') + '</td></tr>';
        });
        document.getElementById('twmcd-database-tables').innerHTML = markup + '</tbody></table>';
    }

    function yesNo(value) {
        return value ? 'Yes' : 'No';
    }

    function renderImages(images) {
        var source = images.source || {};
        var destination = images.destination || {};
        var markup = '<table class="widefat striped"><thead><tr><th>Capability</th><th>Source</th><th>Destination</th></tr></thead><tbody>';
        markup += '<tr><th scope="row">Media migration available</th><td>' + yesNo(source.available) + '</td><td>' + yesNo(destination.available) + '</td></tr>';
        markup += '<tr><th scope="row">Media migration licensed</th><td>' + yesNo(source.licensed) + '</td><td>' + yesNo(destination.licensed) + '</td></tr>';
        markup += '<tr><th scope="row">Media version</th><td>' + escapeHtml(source.version || '—') + '</td><td>' + escapeHtml(destination.version || '—') + '</td></tr>';
        markup += '<tr><th scope="row">Uploads location</th><td><code>' + escapeHtml(source.uploads_dir || '—') + '</code></td><td><code>' + escapeHtml(destination.uploads_dir || '—') + '</code></td></tr>';
        document.getElementById('twmcd-images-report').innerHTML = markup + '</tbody></table>';
    }

    function renderComparison(comparison) {
        var differenceCount = comparison.tables.filter(function (table) {
            return table.status !== 'same';
        }).length;
        var scope = comparison.scope_label ? '<br><span>' + escapeHtml(comparison.scope_label) + '</span>' : '';
        document.getElementById('twmcd-database-summary').innerHTML = '<strong>' + escapeHtml(comparison.source_url) + '</strong> &rarr; <strong>' + escapeHtml(comparison.destination_url) + '</strong>' + scope + '<br>' + differenceCount + ' table metric differences found.<p class="description">' + escapeHtml(comparison.note) + '</p>';
        renderTables(comparison.tables || []);
        renderImages(comparison.images || {});
        loadingCard.hidden = true;
        resultsElement.hidden = false;
    }

    function showError(message) {
        messageElement.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(message) + '</p></div>';
        document.getElementById('twmcd-database-loading').classList.remove('is-active');
    }

    if (!TWMCD_DATABASE_ADMIN.contextToken) {
        showError('No live WP Migrate context was supplied. Return to Migrate and choose Compare Database/Images.');
        return;
    }

    request().then(function (response) {
        if (!response.success) {
            throw new Error(response.data && response.data.message ? response.data.message : TWMCD_DATABASE_ADMIN.labels.comparisonFailed);
        }
        renderComparison(response.data);
    }).catch(function (error) {
        showError(error.message);
    });
}());
