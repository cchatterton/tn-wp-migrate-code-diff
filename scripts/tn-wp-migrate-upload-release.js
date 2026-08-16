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
        requestBody.set('action', 'twmcd_prepare_rollback_package');
        requestBody.set('nonce', TWMCD_UPLOAD.nonce);
        requestBody.delete('release_operation');
        fetch(TWMCD_UPLOAD.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: requestBody
        }).then(function (response) {
            return response.json();
        }).then(function (response) {
            if (!response.success || !response.data || !response.data.download_url) {
                throw new Error(response.data && response.data.message
                    ? response.data.message
                    : TWMCD_UPLOAD.labels.rollbackFailed);
            }

            var downloadLink = document.createElement('a');
            downloadLink.href = response.data.download_url;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            downloadLink.remove();
        }).catch(function (error) {
            showError(error.message || TWMCD_UPLOAD.labels.rollbackFailed);
        }).then(function () {
            setBusy(createRollbackButton, false);
            createRollbackButton.disabled = false;
            installReleaseButton.disabled = false;
        });
    });
}());
