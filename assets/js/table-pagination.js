(function () {
    'use strict';

    var DEFAULT_PAGE_SIZE = 10;

    function createPagination(table, rows, pageSize) {
        if (table.dataset.paginationReady === 'true') return;

        table.dataset.paginationReady = 'true';
        var wrapper = table.closest('.table-wrap') || table.parentElement;
        if (!wrapper) return;

        var state = { page: 1 };
        var totalPages = function () {
            return Math.max(1, Math.ceil(rows.length / pageSize));
        };

        var controls = document.createElement('nav');
        controls.className = 'table-pagination';
        controls.setAttribute('aria-label', 'Paginación de tabla');
        controls.innerHTML =
            '<div class="table-pagination-summary"></div>' +
            '<div class="table-pagination-buttons">' +
            '<button type="button" class="table-page-button table-page-prev">Anterior</button>' +
            '<span class="table-page-indicator" aria-live="polite"></span>' +
            '<button type="button" class="table-page-button table-page-next">Siguiente</button>' +
            '</div>';
        wrapper.appendChild(controls);

        var summary = controls.querySelector('.table-pagination-summary');
        var indicator = controls.querySelector('.table-page-indicator');
        var previous = controls.querySelector('.table-page-prev');
        var next = controls.querySelector('.table-page-next');

        function render() {
            var pages = totalPages();
            state.page = Math.min(Math.max(state.page, 1), pages);
            var start = (state.page - 1) * pageSize;
            var end = start + pageSize;

            rows.forEach(function (row, index) {
                row.hidden = index < start || index >= end;
            });

            summary.textContent = 'Mostrando ' + (start + 1) + '–' + Math.min(end, rows.length) + ' de ' + rows.length + ' registros';
            indicator.textContent = 'Página ' + state.page + ' de ' + pages;
            previous.disabled = state.page === 1;
            next.disabled = state.page === pages;
        }

        previous.addEventListener('click', function () {
            state.page -= 1;
            render();
        });
        next.addEventListener('click', function () {
            state.page += 1;
            render();
        });
        render();
    }

    function paginateTables(root) {
        var scope = root || document;
        scope.querySelectorAll('table:not([data-no-pagination])').forEach(function (table) {
            if (table.closest('.table-pagination')) return;
            var body = table.tBodies[0];
            if (!body) return;
            var rows = Array.prototype.slice.call(body.rows).filter(function (row) {
                return !row.classList.contains('pagination-placeholder') && !row.querySelector('.table-empty');
            });
            createPagination(table, rows, Number(table.dataset.pageSize) || DEFAULT_PAGE_SIZE);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        paginateTables(document);
    });

    window.epsicPaginateTables = paginateTables;
})();
