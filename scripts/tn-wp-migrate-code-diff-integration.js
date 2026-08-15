(function () {
    'use strict';

    var noticeId = 'twmcd-integration-notice';
    var subscribedStore = null;
    var preparing = false;
    var pollAttempts = 0;
    var pollTimer = null;

    function boolValue(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function getStore() {
        return window.WPMDBStore && typeof window.WPMDBStore.getState === 'function'
            ? window.WPMDBStore
            : null;
    }

    function siteIsMultisite(site) {
        if (!site) {
            return false;
        }

        var details = site.site_details || site;
        return boolValue(details.is_multisite);
    }

    function connectionFromDom() {
        var field = document.querySelector('#connect textarea');

        return field && typeof field.value === 'string' ? field.value.trim() : '';
    }

    function intentFromDom() {
        var summary = document.querySelector('#panel-summary-action_buttons .panel-summary');
        var value = summary && summary.textContent ? summary.textContent.trim().toLowerCase() : '';

        return value === 'push' || value === 'pull' ? value : '';
    }

    function multisiteFromDom() {
        var source = document.querySelector('#wpmdb-source-multisite-selector');
        var destination = document.querySelector('#wpmdb-destination-multisite-selector');

        return {
            source_present: Boolean(source),
            destination_present: Boolean(destination),
            selected_subsite: source ? parseInt(source.value, 10) || 0 : 0,
            destination_subsite: destination ? parseInt(destination.value, 10) || 0 : 0
        };
    }

    function getSnapshot() {
        var store = getStore();
        if (!store) {
            return null;
        }

        var state = store.getState() || {};
        var migrations = state.migrations || {};
        var current = migrations.current_migration || {};
        var connectionContainer = migrations.connection_info || {};
        var connectionState = connectionContainer.connection_state || {};
        var multisite = state.multisite_tools || {};
        var storeHasIntent = current.intent === 'push' || current.intent === 'pull';
        var intent = storeHasIntent ? current.intent : intentFromDom();
        var localSite = migrations.local_site || {};
        var remoteSite = migrations.remote_site || {};
        var remoteDetails = remoteSite.site_details || {};
        var localIsMultisite = siteIsMultisite(localSite);
        var remoteIsMultisite = siteIsMultisite(remoteSite);
        var domMultisite = multisiteFromDom();
        var localSource = storeHasIntent && typeof current.localSource === 'boolean'
            ? current.localSource
            : intent === 'push';
        if (!remoteIsMultisite && (domMultisite.destination_present || (!localIsMultisite && domMultisite.source_present))) {
            remoteIsMultisite = true;
        }
        var sourceIsMultisite = localSource ? localIsMultisite : remoteIsMultisite;
        var destinationIsMultisite = localSource ? remoteIsMultisite : localIsMultisite;
        var selectedSubsite = parseInt(multisite.selected_subsite, 10) || domMultisite.selected_subsite;
        var destinationSubsite = parseInt(multisite.destination_subsite, 10) || domMultisite.destination_subsite;
        var twoMultisites = boolValue(current.twoMultisites) || domMultisite.destination_present;
        var multisiteEnabled = boolValue(multisite.enabled) || domMultisite.source_present || domMultisite.destination_present;
        var sourceSubsiteRequired = domMultisite.source_present
            ? !selectedSubsite
            : multisiteEnabled && (sourceIsMultisite || destinationIsMultisite) && !selectedSubsite;
        var destinationSubsiteRequired = domMultisite.destination_present
            ? !destinationSubsite
            : multisiteEnabled && twoMultisites && destinationIsMultisite && !destinationSubsite;

        var connection = typeof connectionState.value === 'string' ? connectionState.value.trim() : '';
        if (!connection && connectionState.url && connectionState.key) {
            connection = connectionState.url + '\n' + connectionState.key;
        }
        if (!connection) {
            connection = connectionFromDom();
        }
        var connected = boolValue(current.connected)
            || Boolean(remoteDetails.home_url || remoteDetails.site_url)
            || Boolean(connectionState.url && connectionState.key)
            || Boolean(connection);

        return {
            has_direction: intent === 'push' || intent === 'pull',
            connection_ready: connected && Boolean(connection),
            ready: !sourceSubsiteRequired && !destinationSubsiteRequired,
            intent: intent,
            connection: connection,
            context: {
                multisite_tools: {
                    enabled: multisiteEnabled,
                    selected_subsite: selectedSubsite,
                    destination_subsite: destinationSubsite,
                    new_prefix: multisite.new_prefix || ''
                },
                migration: {
                    local_source: localSource,
                    two_multisites: twoMultisites,
                    local_is_multisite: localIsMultisite,
                    remote_is_multisite: remoteIsMultisite,
                    scope_label: scopeLabel(sourceIsMultisite, destinationIsMultisite, multisiteEnabled, twoMultisites)
                }
            }
        };
    }

    function scopeLabel(sourceIsMultisite, destinationIsMultisite, enabled, twoMultisites) {
        if (!sourceIsMultisite && !destinationIsMultisite) {
            return 'Single site to single site';
        }
        if (!enabled) {
            if (sourceIsMultisite && destinationIsMultisite) {
                return 'Entire multisite network to entire multisite network';
            }
            return sourceIsMultisite ? 'Entire multisite network to single site' : 'Single site to multisite network';
        }
        if (twoMultisites) {
            return 'Selected source subsite to selected destination subsite';
        }
        return sourceIsMultisite ? 'Selected source subsite to single site' : 'Single site to selected destination subsite';
    }

    function noticeContainer() {
        return document.getElementById('twmcd-integration-notice-mount');
    }

    function placeNotice(notice) {
        var wpMigrateNotice = document.querySelector('#root .migrate-notice.warning');

        if (!wpMigrateNotice || !wpMigrateNotice.parentNode) {
            return;
        }

        if (notice.parentNode !== wpMigrateNotice.parentNode || notice.previousSibling !== wpMigrateNotice) {
            wpMigrateNotice.parentNode.insertBefore(notice, wpMigrateNotice.nextSibling);
        }
    }

    function removeNotice() {
        var notice = document.getElementById(noticeId);
        if (notice) {
            notice.remove();
        }
    }

    function renderNotice() {
        var snapshot = getSnapshot();
        var container = noticeContainer();

        if (!container) {
            removeNotice();
            return;
        }

        var notice = document.getElementById(noticeId);
        if (!notice) {
            notice = document.createElement('div');
            notice.id = noticeId;
            notice.className = 'twmcd-migrate-notice';
            notice.setAttribute('role', 'status');
            container.insertBefore(notice, container.firstChild);
        }

        placeNotice(notice);

        var buttonLabel = preparing ? TWMCD_INTEGRATION.labels.preparing : TWMCD_INTEGRATION.labels.button;
        var message = TWMCD_INTEGRATION.labels.waitingStore;
        var actionAvailable = false;

        if (snapshot) {
            if (!snapshot.has_direction || !snapshot.connection_ready) {
                message = TWMCD_INTEGRATION.labels.waitingConnection;
            } else if (!snapshot.ready) {
                message = TWMCD_INTEGRATION.labels.selectSubsite;
            } else {
                message = TWMCD_INTEGRATION.labels.message;
                actionAvailable = true;
            }
        }
        var markup = '<span class="twmcd-notice-icon" aria-hidden="true">&#8644;</span>'
            + '<strong>' + escapeHtml(message) + '</strong> '
            + (actionAvailable ? '<button type="button" class="button-link twmcd-compare-now"'
            + (preparing ? ' disabled' : '') + '>' + escapeHtml(buttonLabel) + '</button>' : '');

        if (notice.innerHTML === markup) {
            return;
        }
        notice.innerHTML = markup;

        var button = notice.querySelector('.twmcd-compare-now');
        if (button && actionAvailable) {
            button.addEventListener('click', prepareComparison);
        }
    }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function prepareComparison() {
        var snapshot = getSnapshot();
        if (!snapshot || !snapshot.connection_ready || !snapshot.ready || preparing) {
            return;
        }

        preparing = true;
        renderNotice();
        var body = new URLSearchParams({
            action: 'twmcd_prepare_comparison',
            nonce: TWMCD_INTEGRATION.nonce,
            intent: snapshot.intent,
            connection: snapshot.connection,
            context: JSON.stringify(snapshot.context)
        });

        fetch(TWMCD_INTEGRATION.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (response) {
            if (!response.success) {
                throw new Error(response.data && response.data.message ? response.data.message : TWMCD_INTEGRATION.labels.error);
            }
            window.location.href = response.data.redirect_url;
        }).catch(function (error) {
            preparing = false;
            renderNotice();
            window.alert(error.message);
        });
    }

    function initialise() {
        var store = getStore();
        if (store && store !== subscribedStore && typeof store.subscribe === 'function') {
            store.subscribe(renderNotice);
            subscribedStore = store;
        }
        renderNotice();
    }

    function pollForStore() {
        initialise();
        pollAttempts += 1;

        if (pollAttempts >= 40 && pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    window.addEventListener('hashchange', initialise);
    document.addEventListener('input', function (event) {
        if (event.target && event.target.matches && event.target.matches('#connect textarea, #wpmdb-source-multisite-selector, #wpmdb-destination-multisite-selector')) {
            renderNotice();
        }
    });
    document.addEventListener('click', function () {
        window.setTimeout(renderNotice, 0);
    });
    initialise();
    pollTimer = window.setInterval(pollForStore, 250);
}());
