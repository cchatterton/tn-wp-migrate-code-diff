(function () {
    'use strict';

    var form = document.getElementById('twmcd-upload-release-form');
    var createRollbackButton = document.getElementById('twmcd-create-rollback');
    var installReleaseButton = document.getElementById('twmcd-install-release');
    var messageElement = document.getElementById('twmcd-upload-release-message');

    if (!form || !createRollbackButton || !installReleaseButton) {
        return;
    }

    function setBusy(button, busy) {
        var label = button.querySelector('.twmcd-release-button-label');
        var spinner = button.querySelector('.twmcd-release-button-spinner');
        if (!button.hasAttribute('data-default-label')) {
            button.setAttribute('data-default-label', label.textContent);
        }
        button.classList.toggle('is-busy', busy);
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
        spinner.classList.toggle('is-active', busy);
        label.textContent = busy ? button.getAttribute('data-busy-label') : button.getAttribute('data-default-label');
    }

    function showError(message) {
        var notice = document.createElement('div');
        var paragraph = document.createElement('p');
        notice.className = 'notice notice-error inline';
        paragraph.textContent = message;
        notice.appendChild(paragraph);
        messageElement.innerHTML = '';
        messageElement.appendChild(notice);
    }

    function responseError(response) {
        return response.text().then(function (responseText) {
            var errorDocument = new DOMParser().parseFromString(responseText, 'text/html');
            var errorMessage = errorDocument.querySelector('.wp-die-message, p, h1');
            throw new Error(errorMessage && errorMessage.textContent.trim()
                ? errorMessage.textContent.trim()
                : 'The rollback package could not be created.');
        });
    }

    function responseFilename(response) {
        var disposition = response.headers.get('Content-Disposition') || '';
        var match = disposition.match(/filename="?([^";]+)"?/i);
        return match && match[1] ? match[1] : 'release-rollback.zip';
    }

    function downloadResponse(response) {
        var filename = responseFilename(response);
        return response.blob().then(function (archive) {
            var downloadUrl = URL.createObjectURL(archive);
            var downloadLink = document.createElement('a');
            downloadLink.href = downloadUrl;
            downloadLink.download = filename;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            downloadLink.remove();
            window.setTimeout(function () {
                URL.revokeObjectURL(downloadUrl);
            }, 1000);
        });
    }

    form.addEventListener('submit', function (event) {
        var submitter = event.submitter;
        if (!submitter || !form.reportValidity()) {
            return;
        }

        messageElement.innerHTML = '';
        if ('install' === submitter.value) {
            setBusy(installReleaseButton, true);
            createRollbackButton.disabled = true;
            return;
        }

        event.preventDefault();
        setBusy(createRollbackButton, true);
        createRollbackButton.disabled = true;
        installReleaseButton.disabled = true;

        var requestBody = new FormData(form);
        requestBody.set('release_operation', 'create_rollback');
        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            body: requestBody
        }).then(function (response) {
            if (!response.ok || (response.headers.get('Content-Type') || '').indexOf('application/zip') === -1) {
                return responseError(response);
            }
            return downloadResponse(response);
        }).catch(function (error) {
            showError(error.message || 'The rollback package could not be created.');
        }).then(function () {
            setBusy(createRollbackButton, false);
            createRollbackButton.disabled = false;
            installReleaseButton.disabled = false;
        });
    });
}());
