(function () {
    'use strict';

    function isFilterForm(form) {
        if (form.method.toUpperCase() !== 'GET') return false;
        if (form.dataset.manualFilter === 'true') return false;
        return form.matches('.unified-filter, .notif-page-filter, form.card.no-print, .actions-bar.no-print') ||
            !!form.querySelector(
                'input[type="search"], input[name="search"], input[name="q"], ' +
                'input[name="buscar"], input[name="filtro"], select[name], ' +
                'input[name="barrio_id"], input[name="estado"]'
            );
    }

    function submitFilter(form) {
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        form.requestSubmit();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            if (!isFilterForm(form)) return;

            form.dataset.autoFilter = 'true';
            form.classList.add('auto-filter');
            var searchTimer;

            form.querySelectorAll('select').forEach(function (select) {
                select.addEventListener('change', function () {
                    submitFilter(form);
                });
            });

            form.querySelectorAll(
                'input[type="search"], input[name="search"], input[name="q"], ' +
                'input[name="buscar"], input[name="filtro"], input[type="month"], ' +
                'input[name="desde"], input[name="hasta"], input[name="anio"]'
            ).forEach(function (input) {
                input.addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function () {
                        submitFilter(form);
                    }, 450);
                });
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        clearTimeout(searchTimer);
                        submitFilter(form);
                    }
                });
            });
        });
    });
})();
