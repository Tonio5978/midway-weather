(function () {
    'use strict';

    var deferredInstallPrompt = null;
    var installButton = document.getElementById('install-button');
    var iosDialog = document.getElementById('ios-install-dialog');
    var iosDialogClose = document.getElementById('ios-install-close');
    var clientScript = document.getElementById('pwa-client');

    if (!installButton) return;

    function previewMode() {
        return clientScript ? clientScript.getAttribute('data-preview-mode') : '';
    }

    function isStandalone() {
        return previewMode() === 'standalone'
            || window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function isMobileViewport() {
        return window.matchMedia('(max-width: 1024px)').matches;
    }

    function isIOS() {
        if (previewMode() === 'ios') return true;

        var userAgent = window.navigator.userAgent || '';
        var iOSDevice = /iPad|iPhone|iPod/i.test(userAgent);
        var touchMac = window.navigator.platform === 'MacIntel'
            && window.navigator.maxTouchPoints > 1;

        return iOSDevice || touchMac;
    }

    function isIPhone() {
        return /iPhone|iPod/i.test(window.navigator.userAgent || '');
    }

    if (isStandalone() && isIPhone()) {
        document.documentElement.classList.add('iphone-standalone');
    }

    function hideInstallButton() {
        installButton.hidden = true;
    }

    function showInstallButton() {
        if (!isStandalone() && isMobileViewport()) {
            installButton.hidden = false;
        }
    }

    function closeIOSDialog() {
        if (!iosDialog || !iosDialog.open) return;

        if (typeof iosDialog.close === 'function') {
            iosDialog.close();
        } else {
            iosDialog.removeAttribute('open');
            installButton.focus();
        }
    }

    function openIOSDialog() {
        if (!iosDialog || iosDialog.open) return;

        if (typeof iosDialog.showModal === 'function') {
            iosDialog.showModal();
        } else {
            iosDialog.setAttribute('open', '');
        }

        if (iosDialogClose) iosDialogClose.focus();
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallButton();
    });

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        hideInstallButton();
    });

    installButton.addEventListener('click', function () {
        if (isIOS()) {
            openIOSDialog();
            return;
        }

        if (!deferredInstallPrompt) return;

        var promptEvent = deferredInstallPrompt;
        deferredInstallPrompt = null;
        hideInstallButton();
        promptEvent.prompt();
        Promise.resolve(promptEvent.userChoice).then(function (choiceResult) {
            if (choiceResult && choiceResult.outcome === 'accepted') {
                hideInstallButton();
            }
        });
    });

    if (iosDialogClose) {
        iosDialogClose.addEventListener('click', closeIOSDialog);
    }

    if (iosDialog) {
        iosDialog.addEventListener('close', function () {
            installButton.focus();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && iosDialog && iosDialog.open) {
            closeIOSDialog();
        }
    });

    if (isStandalone()) {
        hideInstallButton();
    } else if (isIOS() || previewMode() === 'chromium') {
        showInstallButton();
    }

    if ('serviceWorker' in window.navigator && clientScript) {
        var serviceWorkerUrl = clientScript.getAttribute('data-service-worker-url');
        if (serviceWorkerUrl) {
            window.navigator.serviceWorker.register(serviceWorkerUrl)
                .then(function (registration) {
                    // Do not wait for the browser's periodic update check when
                    // recovering a display from a failed shell-cache install.
                    return registration.update();
                })
                .catch(function () {
                    // PWA support is progressive enhancement. The live
                    // dashboard must continue without an unhandled rejection.
                });
        }
    }
}());
