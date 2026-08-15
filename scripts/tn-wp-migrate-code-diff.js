(function () {
    'use strict';

    var state = { comparison: null, savedProfileUrl: '' };
    var resultsElement = document.getElementById('twmcd-results');
    var groupsElement = document.getElementById('twmcd-groups');
    var messageElement = document.getElementById('twmcd-message');
    var loadingCard = document.getElementById('twmcd-loading-card');
    var profileMessageElement = document.getElementById('twmcd-profile-message');
    var openProfileButton = document.getElementById('twmcd-open-profile');
    var refreshButton = document.getElementById('twmcd-refresh-comparison');
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

    function showProfileMessage(message, isError) {
        profileMessageElement.innerHTML = '<div class="notice notice-' + (isError ? 'error' : 'success')
            + ' inline"><p>' + escapeHtml(message) + '</p></div>';
    }

    function invalidateSavedProfile() {
        state.savedProfileUrl = '';
        openProfileButton.disabled = true;
        profileMessageElement.innerHTML = '';
    }

    function renderGroup(groupKey, packages) {
        var markup = '<section class="twmcd-card" aria-labelledby="twmcd-' + groupKey + '-title">';
        var contentId = 'twmcd-' + groupKey + '-content';
        var selectablePackages = packages.filter(function (packageData) {
            return packageData.status !== 'destination_only' && packageData.selection;
        });
        markup += '<div class="twmcd-group-heading"><h2 id="twmcd-' + groupKey + '-title">';
        markup += '<button type="button" class="button-link twmcd-accordion-toggle" aria-expanded="true" aria-controls="' + contentId + '">';
        markup += '<span class="twmcd-accordion-icon" aria-hidden="true">&#9662;</span> ' + escapeHtml(groupLabels[groupKey]);
        markup += ' <span id="twmcd-' + groupKey + '-selected-count" class="twmcd-selected-count"></span></button></h2>';
        markup += '<div class="twmcd-group-toggles">';
        markup += '<button type="button" class="button-link twmcd-group-toggle" data-group="' + groupKey + '" data-selection-mode="all"' + (!selectablePackages.length ? ' disabled' : '') + '>' + escapeHtml(TWMCD_ADMIN.labels.selectAll) + '</button>';
        markup += '<span aria-hidden="true"> / </span>';
        markup += '<button type="button" class="button-link twmcd-group-toggle" data-group="' + groupKey + '" data-selection-mode="none"' + (!selectablePackages.length ? ' disabled' : '') + '>' + escapeHtml(TWMCD_ADMIN.labels.deselectAll) + '</button>';
        markup += '<span aria-hidden="true"> / </span>';
        markup += '<button type="button" class="button-link twmcd-group-toggle" data-group="' + groupKey + '" data-selection-mode="recommended"' + (!selectablePackages.length ? ' disabled' : '') + '>' + escapeHtml(TWMCD_ADMIN.labels.recommended) + '</button>';
        markup += '</div></div><div id="' + contentId + '" class="twmcd-accordion-content">';

        if (!packages.length) {
            return markup + '<p>' + escapeHtml(TWMCD_ADMIN.labels.noInventory) + '</p></div></section>';
        }

        markup += '<div class="twmcd-table-scroll"><table class="widefat striped">';
        markup += '<thead><tr><td class="check-column"></td><th scope="col">Package</th><th scope="col">Version status</th><th scope="col">Source</th><th scope="col">Destination</th><th scope="col">Source activation</th><th scope="col">Destination activation</th></tr></thead><tbody>';

        packages.forEach(function (packageData, packageIndex) {
            var selectedByDefault = Boolean(
                (typeof packageData.initial_selected === 'boolean'
                    ? packageData.initial_selected
                    : packageData.default_selected) && packageData.selection
            );
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

        return markup + '</tbody></table></div></div></section>';
    }

    function renderComparison(comparison) {
        var differenceCount = 0;
        state.comparison = comparison;
        if (comparison.comparison_token && window.history && typeof window.history.replaceState === 'function') {
            var reportUrl = new URL(window.location.href);
            reportUrl.searchParams.set('twmcd_comparison', comparison.comparison_token);
            window.history.replaceState(null, '', reportUrl.toString());
        }
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
        if (comparison.profile_selection_applied) {
            document.getElementById('twmcd-summary').insertAdjacentHTML(
                'beforeend',
                '<p class="description"><strong>' + escapeHtml(
                    TWMCD_ADMIN.labels.profileSelectionApplied.replace('%s', comparison.profile_selection_name || TWMCD_ADMIN.labels.savedProfile)
                ) + '</strong></p>'
            );
        }
        var releaseButton = document.getElementById('twmcd-create-release-package');
        releaseButton.disabled = !comparison.release_package_available;
        document.getElementById('twmcd-release-package-note').textContent = comparison.release_package_note || '';
        loadingCard.hidden = true;
        resultsElement.hidden = false;
        updateSelectionCounts();
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

    function updateSelectionCounts() {
        var total = 0;
        Object.keys(groupLabels).forEach(function (groupKey) {
            var count = document.querySelectorAll('.twmcd-package-selection[data-group="' + groupKey + '"]:checked').length;
            var countElement = document.getElementById('twmcd-' + groupKey + '-selected-count');
            total += count;
            if (countElement) {
                countElement.textContent = '(' + TWMCD_ADMIN.labels.selectedCount.replace('%d', count) + ')';
            }
        });

        var totalElement = document.getElementById('twmcd-release-selection-count');
        if (totalElement) {
            totalElement.textContent = TWMCD_ADMIN.labels.selectedForRelease.replace('%d', total);
        }
    }

    groupsElement.addEventListener('click', function (event) {
        var accordionToggle = event.target.closest ? event.target.closest('.twmcd-accordion-toggle') : null;
        if (accordionToggle) {
            var content = document.getElementById(accordionToggle.getAttribute('aria-controls'));
            var expanded = accordionToggle.getAttribute('aria-expanded') === 'true';
            accordionToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            if (content) {
                content.hidden = expanded;
            }
            return;
        }

        var toggle = event.target.closest ? event.target.closest('.twmcd-group-toggle') : null;
        if (!toggle || toggle.disabled) {
            return;
        }

        var groupKey = toggle.getAttribute('data-group');
        var selectionMode = toggle.getAttribute('data-selection-mode');
        document.querySelectorAll('.twmcd-package-selection[data-group="' + groupKey + '"]:not(:disabled)').forEach(function (checkbox) {
            var packageIndex = parseInt(checkbox.getAttribute('data-index'), 10);
            var packageData = state.comparison.groups[groupKey][packageIndex];
            checkbox.checked = selectionMode === 'all'
                || (selectionMode === 'recommended' && packageData && packageData.default_selected);
        });
        invalidateSavedProfile();
        updateSelectionCounts();
    });

    groupsElement.addEventListener('change', function (event) {
        if (event.target && event.target.classList.contains('twmcd-package-selection')) {
            invalidateSavedProfile();
            updateSelectionCounts();
        }
    });

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
            state.savedProfileUrl = response.data.redirect_url;
            openProfileButton.disabled = false;
            showProfileMessage(response.data.message || TWMCD_ADMIN.labels.profileSaved, false);
            saveButton.disabled = false;
        }).catch(function (error) {
            showProfileMessage(error.message, true);
            saveButton.disabled = false;
        });
    });

    openProfileButton.addEventListener('click', function () {
        if (state.savedProfileUrl) {
            window.location.href = state.savedProfileUrl;
        }
    });

    document.getElementById('twmcd-profile-name').addEventListener('input', invalidateSavedProfile);

    document.getElementById('twmcd-create-release-package').addEventListener('click', function () {
        var form = document.getElementById('twmcd-release-package-form');
        var selection = selectedPackages();
        var selectedCount = selection.plugins.length + selection.themes.length + selection.muplugins.length;

        if (!state.comparison || !state.comparison.release_package_available || !selectedCount) {
            showProfileMessage(TWMCD_ADMIN.labels.releasePackageEmpty, true);
            return;
        }

        form.elements.release_name.value = document.getElementById('twmcd-profile-name').value;
        form.elements.comparison_token.value = state.comparison.comparison_token;
        form.elements.selection.value = JSON.stringify(selection);
        form.submit();
    });

    function loadComparison() {
        var requestData = state.comparison && state.comparison.comparison_token
            ? { comparison_token: state.comparison.comparison_token }
            : (TWMCD_ADMIN.comparisonToken
                ? { comparison_token: TWMCD_ADMIN.comparisonToken }
                : { context_token: TWMCD_ADMIN.contextToken });
        refreshButton.disabled = true;
        invalidateSavedProfile();
        resultsElement.hidden = true;
        loadingCard.hidden = false;
        document.getElementById('twmcd-loading').classList.add('is-active');
        messageElement.innerHTML = '';

        request('twmcd_compare_code', requestData).then(function (response) {
            if (!response.success) {
                throw new Error(response.data && response.data.message ? response.data.message : TWMCD_ADMIN.labels.comparisonFailed);
            }
            renderComparison(response.data);
            refreshButton.disabled = false;
        }).catch(function (error) {
            showError(error.message);
            document.getElementById('twmcd-loading').classList.remove('is-active');
            refreshButton.disabled = false;
        });
    }

    refreshButton.addEventListener('click', loadComparison);

    if (!TWMCD_ADMIN.contextToken && !TWMCD_ADMIN.comparisonToken) {
        showError('No live WP Migrate context was supplied. Return to Migrate and choose Compare Code.');
        return;
    }

    loadComparison();
}());
