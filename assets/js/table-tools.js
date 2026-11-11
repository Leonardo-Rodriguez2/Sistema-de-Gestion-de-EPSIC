(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function exportTable(table, filename) {
        var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (cell) {
            return '<th>' + escapeHtml(cell.textContent.trim()) + '</th>';
        }).join('');
        var rows = Array.prototype.filter.call(table.tBodies[0] ? table.tBodies[0].rows : [], function (row) {
            return !row.hidden && !row.classList.contains('pagination-placeholder') &&
                !row.querySelector('.table-empty');
        }).map(function (row) {
            return '<tr>' + Array.prototype.map.call(row.cells, function (cell) {
                return '<td>' + escapeHtml(cell.textContent.replace(/\s+/g, ' ').trim()) + '</td>';
            }).join('') + '</tr>';
        }).join('');
        var html = '<html><head><meta charset="UTF-8"></head><body><table><thead><tr>' +
            headers + '</tr></thead><tbody>' + rows + '</tbody></table></body></html>';
        var blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename + '_' + new Date().toISOString().slice(0, 10) + '.xls';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    function addTools(table, index) {
        if (!table.tBodies[0] || table.dataset.noTools === 'true') return;
        if (table.closest('.invoice-table, .report-print-head')) return;

        var wrap = table.closest('.table-wrap') || table.parentElement;
        if (!wrap || wrap.querySelector(':scope > .table-tools')) return;
        var pagination = wrap.querySelector(':scope > .table-pagination');
        if (pagination) pagination.remove();

        var tools = document.createElement('div');
        tools.className = 'table-tools no-print';
        var searchMarkup = table.dataset.noTableSearch === 'true'
            ? ''
            : '<label class="table-tools-search">Buscar <input type="search" placeholder="Nombre, estado, ubicación..." aria-label="Buscar en la tabla"></label>';
        tools.innerHTML = searchMarkup +
            '<div class="table-tools-actions"><span class="table-tools-count"></span>' +
            '<button type="button" class="btn-primary table-tools-excel">📊 Excel</button>' +
            '<button type="button" class="btn-primary table-tools-print">🖨️ Imprimir</button></div>';
        wrap.insertBefore(tools, table);

        var search = tools.querySelector('input');
        var count = tools.querySelector('.table-tools-count');
        var rows = Array.prototype.filter.call(table.tBodies[0].rows, function (row) {
            return !row.querySelector('.table-empty');
        });
        function render() {
            var query = search ? search.value.trim().toLocaleLowerCase() : '';
            var visible = 0;
            rows.forEach(function (row) {
                var match = !query || row.textContent.toLocaleLowerCase().indexOf(query) !== -1;
                row.dataset.tableSearchHidden = match ? 'false' : 'true';
                row.hidden = !match;
                if (match) visible++;
            });
            count.textContent = visible + ' registro' + (visible === 1 ? '' : 's');
        }
        if (search) search.addEventListener('input', render);
        tools.querySelector('.table-tools-excel').addEventListener('click', function () {
            exportTable(table, 'reporte_tabla_' + (index + 1));
        });
        tools.querySelector('.table-tools-print').addEventListener('click', function () {
            window.print();
        });
        render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var hasReportToolbar = !!document.querySelector('.report-toolbar');
        document.querySelectorAll('.table-wrap > table, .card > table').forEach(function (table, index) {
            if (!hasReportToolbar) addTools(table, index);
        });
    });
})();
