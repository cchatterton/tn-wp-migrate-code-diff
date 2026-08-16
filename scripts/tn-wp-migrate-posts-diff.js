(function () {
    'use strict';

    var state = { comparison: null };
    var results = document.getElementById('twmcd-posts-results');
    var loadingCard = document.getElementById('twmcd-posts-loading-card');
    var message = document.getElementById('twmcd-posts-message');
    var refresh = document.getElementById('twmcd-posts-refresh');
    var packageButton = document.getElementById('twmcd-create-posts-package');
    var packageMessage = document.getElementById('twmcd-posts-package-message');
    if (!results) { return; }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function request(action, data) {
        var body = new URLSearchParams(Object.assign({ action: action, nonce: TWMCD_POSTS_ADMIN.nonce }, data));
        return fetch(TWMCD_POSTS_ADMIN.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
            .then(function (response) { return response.json(); });
    }

    function statusLabel(status) {
        var labels = TWMCD_POSTS_ADMIN.labels;
        return { same: labels.same, different: labels.different, source_only: labels.sourceOnly, destination_only: labels.destinationOnly }[status] || status;
    }

    function selectedIdentities() {
        return Array.prototype.map.call(document.querySelectorAll('.twmcd-post-selection:checked'), function (input) {
            return input.getAttribute('data-identity');
        });
    }

    function updateCounts() {
        var total = 0;
        Array.prototype.forEach.call(document.querySelectorAll('.twmcd-posts-group'), function (card) {
            var count = card.querySelectorAll('.twmcd-post-selection:checked').length;
            total += count;
            card.querySelector('.twmcd-selected-count').textContent = '(' + count + ' selected)';
        });
        document.getElementById('twmcd-posts-selection-count').textContent = total + ' posts selected for release.';
    }

    function renderGroup(postType, posts, index) {
        var id = 'twmcd-post-type-' + index;
        var rows = posts.map(function (post) {
            var checked = post.default_selected && post.selection;
            var disabled = !post.selection;
            return '<tr><th scope="row" class="check-column"><input class="twmcd-post-selection" type="checkbox" data-identity="' + escapeHtml(post.identity) + '" data-recommended="' + (post.default_selected ? '1' : '0') + '"'
                + (checked ? ' checked' : '') + (disabled ? ' disabled' : '') + ' aria-label="Select ' + escapeHtml(post.title) + '"></th>'
                + '<td><strong>' + escapeHtml(post.title || '(no title)') + '</strong></td>'
                + '<td><span class="twmcd-status twmcd-status-' + escapeHtml(post.status) + '">' + escapeHtml(statusLabel(post.status)) + '</span></td>'
                + '<td>' + escapeHtml(post.source_status || '—') + '</td><td>' + escapeHtml(post.destination_status || '—') + '</td>'
                + '<td>' + (post.portable ? escapeHtml(post.identity) : 'Unmatched ID only') + '</td></tr>';
        }).join('');

        return '<section class="twmcd-card twmcd-posts-group"><div class="twmcd-group-heading"><h2><button type="button" class="button-link twmcd-accordion-toggle" aria-expanded="true" aria-controls="' + id + '"><span class="twmcd-accordion-icon" aria-hidden="true">&#9662;</span> '
            + escapeHtml(postType) + ' <span class="twmcd-selected-count"></span></button></h2><div class="twmcd-group-toggles">'
            + '<button class="button-link" type="button" data-select="all">Select all</button> / <button class="button-link" type="button" data-select="none">Deselect all</button> / <button class="button-link" type="button" data-select="recommended">Recommended</button></div></div>'
            + '<div id="' + id + '" class="twmcd-table-scroll twmcd-accordion-content"><table class="widefat striped"><thead><tr><td class="check-column"></td><th>Post</th><th>Status</th><th>Source status</th><th>Destination status</th><th>Identity</th></tr></thead><tbody>' + rows + '</tbody></table></div></section>';
    }

    function render(data) {
        state.comparison = data;
        var groupNames = Object.keys(data.groups || {});
        var differenceCount = 0;
        groupNames.forEach(function (name) {
            differenceCount += data.groups[name].filter(function (post) { return post.status !== 'same'; }).length;
        });
        var scope = data.scope_label ? '<br>' + escapeHtml(data.scope_label) : '';
        document.getElementById('twmcd-posts-summary').innerHTML = '<strong>' + escapeHtml(data.source_url) + '</strong> &rarr; <strong>' + escapeHtml(data.destination_url) + '</strong>' + scope + '<br>' + differenceCount + ' post differences found.';
        document.getElementById('twmcd-posts-groups').innerHTML = groupNames.map(function (name, index) { return renderGroup(name, data.groups[name], index); }).join('');
        packageButton.disabled = !data.package_available;
        loadingCard.hidden = true;
        results.hidden = false;
        updateCounts();
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
            var mode = select.getAttribute('data-select');
            var card = select.closest('.twmcd-posts-group');
            Array.prototype.forEach.call(card.querySelectorAll('.twmcd-post-selection:not(:disabled)'), function (input) {
                input.checked = mode === 'all' || (mode === 'recommended' && input.getAttribute('data-recommended') === '1');
            });
            updateCounts();
        }
    });
    results.addEventListener('change', updateCounts);

    function setBusy(busy) {
        packageButton.disabled = busy || !state.comparison || !state.comparison.package_available;
        packageButton.classList.toggle('is-busy', busy);
        packageButton.setAttribute('aria-busy', busy ? 'true' : 'false');
        packageButton.querySelector('.spinner').classList.toggle('is-active', busy);
        packageButton.querySelector('.twmcd-release-button-label').textContent = busy ? TWMCD_POSTS_ADMIN.labels.creating : TWMCD_POSTS_ADMIN.labels.create;
    }

    packageButton.addEventListener('click', function () {
        var selection = selectedIdentities();
        if (!selection.length) {
            packageMessage.innerHTML = '<div class="notice notice-error inline"><p>Select at least one post operation.</p></div>';
            return;
        }
        setBusy(true);
        packageMessage.innerHTML = '';
        request('twmcd_prepare_post_release_package', { comparison_token: state.comparison.comparison_token, selection: JSON.stringify(selection) })
            .then(function (response) {
                if (!response.success || !response.data || !response.data.download_url) {
                    throw new Error(response.data && response.data.message ? response.data.message : TWMCD_POSTS_ADMIN.labels.packageFailed);
                }
                var link = document.createElement('a');
                link.href = response.data.download_url;
                document.body.appendChild(link);
                link.click();
                link.remove();
            }).catch(function (error) {
                packageMessage.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(error.message) + '</p></div>';
            }).then(function () { setBusy(false); });
    });

    function load() {
        if (!TWMCD_POSTS_ADMIN.contextToken) {
            message.innerHTML = '<div class="notice notice-error inline"><p>No live WP Migrate context was supplied. Return to Migrate and choose Compare Posts.</p></div>';
            return;
        }
        refresh.disabled = true;
        results.hidden = true;
        loadingCard.hidden = false;
        message.innerHTML = '';
        request('twmcd_compare_posts', { context_token: TWMCD_POSTS_ADMIN.contextToken })
            .then(function (response) {
                if (!response.success) { throw new Error(response.data && response.data.message ? response.data.message : TWMCD_POSTS_ADMIN.labels.comparisonFailed); }
                render(response.data);
            }).catch(function (error) {
                message.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(error.message) + '</p></div>';
            }).then(function () { refresh.disabled = false; });
    }

    refresh.addEventListener('click', load);
    load();
}());
