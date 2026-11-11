(function () {
    'use strict';

    var activeModal = null;
    var activeResolve = null;

    function ensureModal() {
        if (document.getElementById('epsicSystemModal')) {
            return document.getElementById('epsicSystemModal');
        }

        var modal = document.createElement('div');
        modal.id = 'epsicSystemModal';
        modal.className = 'epsic-modal';
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="epsic-modal-backdrop" data-epsic-close></div>' +
            '<section class="epsic-modal-box" role="dialog" aria-modal="true" aria-labelledby="epsicModalTitle">' +
            '<div class="epsic-modal-icon" id="epsicModalIcon">!</div>' +
            '<div class="epsic-modal-body">' +
            '<span class="epsic-modal-eyebrow">EPSIC</span>' +
            '<h2 id="epsicModalTitle">Confirmar acción</h2>' +
            '<p id="epsicModalMessage"></p>' +
            '</div>' +
            '<div class="epsic-modal-actions">' +
            '<button type="button" class="epsic-modal-cancel" data-epsic-cancel>Cancelar</button>' +
            '<button type="button" class="epsic-modal-confirm" data-epsic-confirm>Confirmar</button>' +
            '</div>' +
            '</section>';
        document.body.appendChild(modal);
        return modal;
    }

    function closeModal(result) {
        if (!activeModal) return;
        activeModal.classList.remove('is-open');
        activeModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('epsic-modal-open');
        var resolve = activeResolve;
        activeModal = null;
        activeResolve = null;
        if (resolve) resolve(result);
    }

    window.epsicConfirm = function (message, options) {
        var modal = ensureModal();
        var settings = options || {};
        modal.querySelector('#epsicModalTitle').textContent = settings.title || 'Confirmar acción';
        modal.querySelector('#epsicModalMessage').textContent = message || '¿Deseas continuar con esta acción?';
        modal.querySelector('#epsicModalIcon').textContent = settings.icon || '?';
        modal.querySelector('[data-epsic-confirm]').textContent = settings.confirmText || 'Confirmar';
        modal.querySelector('[data-epsic-confirm]').className = 'epsic-modal-confirm ' + (settings.danger ? 'is-danger' : '');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('epsic-modal-open');
        modal.querySelector('[data-epsic-confirm]').focus();
        return new Promise(function (resolve) {
            activeModal = modal;
            activeResolve = resolve;
        });
    };

    window.epsicAlert = function (message, options) {
        var settings = Object.assign({}, options || {}, { confirmText: 'Entendido', title: (options && options.title) || 'Aviso' });
        return window.epsicConfirm(message, settings);
    };

    function messageFromHandler(source) {
        var match = source.match(/confirm\(\s*(['"`])([\s\S]*?)\1\s*\)/);
        if (!match) return '¿Deseas continuar con esta acción?';
        return match[2].replace(/\\n/g, '\n').replace(/\\"/g, '"').replace(/\\'/g, "'");
    }

    function replaceNativeConfirmations() {
        document.querySelectorAll('[onsubmit*="confirm("], [onclick*="confirm("]').forEach(function (element) {
            var attribute = element.hasAttribute('onsubmit') ? 'onsubmit' : 'onclick';
            var source = element.getAttribute(attribute);
            if (!source) return;

            var message = messageFromHandler(source);
            element.removeAttribute(attribute);
            element.dataset.epsicConfirmMessage = message;
            element.dataset.epsicConfirmAttribute = attribute;

            if (attribute === 'onsubmit') {
                element.addEventListener('submit', function (event) {
                    if (element.dataset.epsicConfirmed === 'yes') {
                        delete element.dataset.epsicConfirmed;
                        return;
                    }
                    event.preventDefault();
                    window.epsicConfirm(message, { danger: /baja|anular|rechazar|revocar/i.test(message), icon: '!' }).then(function (confirmed) {
                        if (!confirmed) return;
                        element.dataset.epsicConfirmed = 'yes';
                        if (typeof element.requestSubmit === 'function') {
                            element.requestSubmit();
                        } else {
                            HTMLFormElement.prototype.submit.call(element);
                        }
                    });
                });
            } else {
                element.addEventListener('click', function (event) {
                    event.preventDefault();
                    window.epsicConfirm(message, { danger: /baja|anular|rechazar|revocar/i.test(message), icon: '!' }).then(function (confirmed) {
                        if (!confirmed) return;
                        var form = element.form;
                        if (form) {
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit(element);
                            } else {
                                HTMLFormElement.prototype.submit.call(form);
                            }
                        }
                    });
                });
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-epsic-confirm]')) closeModal(true);
        if (event.target.closest('[data-epsic-cancel]') || event.target.closest('[data-epsic-close]')) closeModal(false);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeModal) closeModal(false);
    });
    document.addEventListener('DOMContentLoaded', replaceNativeConfirmations);
    if (window.MutationObserver) {
        new MutationObserver(replaceNativeConfirmations).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
