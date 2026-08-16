(function () {
    'use strict';

    var months = document.getElementById('twmcd-history-months');
    var search = document.getElementById('twmcd-history-search');
    var searchStatus = document.getElementById('twmcd-history-search-status');
    var openMonth = '';
    var searchTimer = null;
    var monthContent = {};

    if (!months || !search) {
        return;
    }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function request(action, data) {
        var body = new URLSearchParams(Object.assign({
            action: action,
            nonce: TWMCD_RELEASE_HISTORY.nonce
        }, data));

        return fetch(TWMCD_RELEASE_HISTORY.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (response) {
            return response.json();
        });
    }

    function escapeRegularExpression(value) {
        return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlight(container, keyword) {
        if (!keyword) {
            return;
        }

        var expression = new RegExp('(' + escapeRegularExpression(keyword) + ')', 'gi');
        var walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
        var nodes = [];
        var node;
        while ((node = walker.nextNode())) {
            if (node.nodeValue && expression.test(node.nodeValue)) {
                nodes.push(node);
            }
            expression.lastIndex = 0;
        }

        nodes.forEach(function (textNode) {
            var parts = textNode.nodeValue.split(expression);
            var fragment = document.createDocumentFragment();
            parts.forEach(function (part, index) {
                if (index % 2) {
                    var mark = document.createElement('mark');
                    mark.textContent = part;
                    fragment.appendChild(mark);
                } else if (part) {
                    fragment.appendChild(document.createTextNode(part));
                }
            });
            textNode.parentNode.replaceChild(fragment, textNode);
        });
    }

    function renderMonth(section) {
        var month = section.getAttribute('data-month');
        var content = section.querySelector('.twmcd-history-month-content');
        if (!monthContent[month]) {
            return;
        }
        content.innerHTML = monthContent[month];
        highlight(content, search.value.trim());
    }

    function closeOtherMonths(month) {
        Array.prototype.forEach.call(months.querySelectorAll('.twmcd-history-month'), function (section) {
            if (section.getAttribute('data-month') === month) {
                return;
            }
            section.querySelector('.twmcd-history-month-toggle').setAttribute('aria-expanded', 'false');
            section.querySelector('.twmcd-history-month-content').hidden = true;
        });
    }

    function openHistoryMonth(section) {
        var month = section.getAttribute('data-month');
        var button = section.querySelector('.twmcd-history-month-toggle');
        var content = section.querySelector('.twmcd-history-month-content');
        if (openMonth === month && button.getAttribute('aria-expanded') === 'true') {
            return;
        }

        closeOtherMonths(month);
        openMonth = month;
        button.setAttribute('aria-expanded', 'true');
        content.hidden = false;
        if (monthContent[month]) {
            renderMonth(section);
            return;
        }

        content.innerHTML = '<p class="twmcd-history-loading"><span class="spinner is-active" aria-hidden="true"></span> ' + TWMCD_RELEASE_HISTORY.labels.loading + '</p>';
        request('twmcd_get_release_history_month', { month: month }).then(function (response) {
            if (!response.success || !response.data || typeof response.data.html !== 'string') {
                throw new Error(response.data && response.data.message ? response.data.message : TWMCD_RELEASE_HISTORY.labels.loadFailed);
            }
            monthContent[month] = response.data.html;
            if (openMonth === month) {
                renderMonth(section);
            }
        }).catch(function (error) {
            content.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(error.message) + '</p></div>';
        });
    }

    function matchLabel(count) {
        if (count === 1) {
            return TWMCD_RELEASE_HISTORY.labels.oneMatch;
        }
        return TWMCD_RELEASE_HISTORY.labels.manyMatches.replace('%d', count);
    }

    function updateSearchResults(response, keyword) {
        var matches = response.data && response.data.matches ? response.data.matches : {};
        var total = response.data && response.data.total ? Number(response.data.total) : 0;
        Array.prototype.forEach.call(months.querySelectorAll('.twmcd-history-month'), function (section) {
            var count = Number(matches[section.getAttribute('data-month')] || 0);
            var counter = section.querySelector('.twmcd-history-match-count');
            counter.hidden = !keyword || count < 1;
            counter.textContent = count > 0 ? matchLabel(count) : '';
        });
        searchStatus.textContent = keyword
            ? (total > 0 ? matchLabel(total) : TWMCD_RELEASE_HISTORY.labels.noMatches)
            : '';

        var current = openMonth ? months.querySelector('[data-month="' + openMonth + '"]') : null;
        if (current && monthContent[openMonth]) {
            renderMonth(current);
        }
    }

    function searchHistory() {
        var keyword = search.value.trim();
        if (!keyword) {
            updateSearchResults({ data: { matches: {}, total: 0 } }, '');
            return;
        }

        searchStatus.textContent = TWMCD_RELEASE_HISTORY.labels.searching;
        request('twmcd_search_release_history', { search: keyword }).then(function (response) {
            if (!response.success) {
                throw new Error(response.data && response.data.message ? response.data.message : TWMCD_RELEASE_HISTORY.labels.searchFailed);
            }
            if (keyword === search.value.trim()) {
                updateSearchResults(response, keyword);
            }
        }).catch(function (error) {
            searchStatus.textContent = error.message;
        });
    }

    months.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('.twmcd-history-month-toggle');
        if (button) {
            openHistoryMonth(button.closest('.twmcd-history-month'));
        }
    });

    search.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(searchHistory, 300);
    });

    var defaultMonth = months.querySelector('[data-default-open="1"]');
    if (defaultMonth) {
        openHistoryMonth(defaultMonth);
    }
}());
