(function () {
    'use strict';

    var state = { comparison: null };
    var resultsElement = document.getElementById('twmcd-results');
    var groupsElement = document.getElementById('twmcd-groups');
    var messageElement = document.getElementById('twmcd-message');
    var loadingCard = document.getElementById('twmcd-loading-card');
    var groupLabels = {
        plugins: 'Plugins',
        themes: 'Themes',
        muplugins: 'Must-use plugins'
    };

    if (!resultsElement) {
        return;
    }

    function request(action, data) {
        var requestBody = new URLSearchParams(Object.assign({
            action: action,
            nonce: TWMCD_ADMIN.nonce
        }, data));

        return fetch(TWMCD_ADMIN.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: requestBody
        }).then(function (response) {
            return response.json();
        });
    }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function statusLabel(status) {
        var labels = {
            same: TWMCD_ADMIN.labels.same,
            different: TWMCD_ADMIN.labels.different,
            source_only: TWMCD_ADMIN.labels.sourceOnly,
            destination_only: TWMCD_ADMIN.labels.destinationOnly
        };
        return labels[status] || status;
    }

    function activationLabel(status) {
        var labels = {
            inactive: TWMCD_ADMIN.labels.inactive,
            site_active: TWMCD_ADMIN.labels.siteActive,
            network_active: TWMCD_ADMIN.labels.networkActive,
            active_in_network: TWMCD_ADMIN.labels.activeInNetwork,
            always_active: TWMCD_ADMIN.labels.alwaysActive,
            not_installed: TWMCD_ADMIN.labels.notInstalled,
            unknown: TWMCD_ADMIN.labels.unknown
        };
        return labels[status] || TWMCD_ADMIN.labels.unknown;
    }

    function showError(errorMessage) {
        messageElement.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(errorMessage) + '</p></div>';
    }

    function renderGroup(groupKey, packages) {
        var markup = '<section class="twmcd-card" aria-labelledby="twmcd-' + groupKey + '-title">';
        markup += '<h2 id="twmcd-' + groupKey + '-title">' + escapeHtml(groupLabels[groupKey]) + '</h2>';

        if (!packages.length) {
            return markup + '<p>' + escapeHtml(TWMCD_ADMIN.labels.noInventory) + '</p></section>';
        }

        markup += '<div class="twmcd-table-scroll"><table class="widefat striped">';
        markup += '<thead><tr><td class="check-column"></td><th scope="col">Package</th><th scope="col">Version status</th><th scope="col">Source</th><th scope="col">Destination</th><th scope="col">Source activation</th><th scope="col">Destination activation</th></tr></thead><tbody>';

        packages.forEach(function (packageData, packageIndex) {
            var selectedByDefault = (packageData.status === 'different' || packageData.status === 'source_only') && packageData.selection;
            var selectionDisabled = packageData.status === 'destination_only' || !packageData.selection;

            markup += '<tr><th scope="row" class="check-column">';
            markup += '<input class="twmcd-package-selection" type="checkbox" aria-label="Select ' + escapeHtml(packageData.name) + '" data-group="' + groupKey + '" data-index="' + packageIndex + '"' + (selectedByDefault ? ' checked' : '') + (selectionDisabled ? ' disabled' : '') + '>';
            markup += '</th><td><strong>' + escapeHtml(packageData.name) + '</strong><br><code>' + escapeHtml(packageData.key) + '</code></td>';
            markup += '<td><span class="twmcd-status twmcd-status-' + packageData.status + '">' + escapeHtml(statusLabel(packageData.status)) + '</span></td>';
            markup += '<td>' + escapeHtml(packageData.source_version || '—') + '</td>';
            markup += '<td>' + escapeHtml(packageData.destination_version || '—') + '</td>';
            markup += '<td>' + escapeHtml(activationLabel(packageData.source_activation)) + '</td>';
            markup += '<td>' + escapeHtml(activationLabel(packageData.destination_activation)) + '</td></tr>';
        });

        return markup + '</tbody></table></div></section>';
    }

    function renderComparison(comparison) {
        var differenceCount = 0;
        state.comparison = comparison;
        groupsElement.innerHTML = '';

        Object.keys(groupLabels).forEach(function (groupKey) {
            var packages = comparison.groups[groupKey] || [];
            differenceCount += packages.filter(function (packageData) {
                return packageData.status !== 'same';
            }).length;
            groupsElement.insertAdjacentHTML('beforeend', renderGroup(groupKey, packages));
        });

        var scope = comparison.scope_label ? '<br><span>' + escapeHtml(comparison.scope_label) + '</span>' : '';
        document.getElementById('twmcd-summary').innerHTML = '<strong>' + escapeHtml(comparison.source_url) + '</strong> &rarr; <strong>' + escapeHtml(comparison.destination_url) + '</strong>' + scope + '<br>' + differenceCount + ' ' + escapeHtml(TWMCD_ADMIN.labels.differencesFound) + '<p class="description">' + escapeHtml(comparison.note) + '</p>';
        loadingCard.hidden = true;
        resultsElement.hidden = false;
    }

    function selectedPackages() {
        var selection = { plugins: [], themes: [], muplugins: [] };
        document.querySelectorAll('.twmcd-package-selection:checked').forEach(function (checkbox) {
            var groupKey = checkbox.getAttribute('data-group');
            var packageIndex = parseInt(checkbox.getAttribute('data-index'), 10);
            var packageData = state.comparison.groups[groupKey][packageIndex];
            if (packageData && packageData.selection) {
                selection[groupKey].push(packageData.selection);
            }
        });
        return selection;
    }

    document.getElementById('twmcd-save-profile').addEventListener('click', function () {
        var saveButton = this;
        saveButton.disabled = true;
        request('twmcd_save_profile', {
            profile_name: document.getElementById('twmcd-profile-name').value,
            comparison_token: state.comparison.comparison_token,
            selection: JSON.stringify(selectedPackages())
        }).then(function (response) {
            if (!response.success) {
                throw new Error(response.data && response.data.message ? response.data.message : TWMCD_ADMIN.labels.saveFailed);
            }
            window.location.href = response.data.redirect_url;
        }).catch(function (error) {
            showError(error.message);
            saveButton.disabled = false;
        });
    });

    if (!TWMCD_ADMIN.contextToken) {
        showError('No live WP Migrate context was supplied. Return to Migrate and choose Compare now.');
        return;
    }

    request('twmcd_compare_code', { context_token: TWMCD_ADMIN.contextToken }).then(function (response) {
        if (!response.success) {
            throw new Error(response.data && response.data.message ? response.data.message : TWMCD_ADMIN.labels.comparisonFailed);
        }
        renderComparison(response.data);
    }).catch(function (error) {
        showError(error.message);
        document.getElementById('twmcd-loading').classList.remove('is-active');
    });
}());
