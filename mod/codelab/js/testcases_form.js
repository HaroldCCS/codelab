/**
 * CodeLab — Gestión dinámica de casos de prueba en el formulario.
 * Script normal (no AMD). La config viene de window.CODELAB_FORM_CFG.
 */
(function() {
    'use strict';

    function init() {
        var cfg       = window.CODELAB_FORM_CFG || {};
        var container = document.getElementById(cfg.containerid || 'codelab-testcases-container');
        var addBtn    = document.getElementById('codelab-add-testcase');
        var jsonField = document.querySelector('[name="testcases_json"]');
        var form      = document.querySelector('form#mform1') || document.querySelector('form.mform') || document.querySelector('form');

        if (!container) { return; }

        /* Botón + Agregar caso de prueba */
        if (addBtn) {
            addBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addRow(container);
                serialize(container, jsonField);
            });
        }

        /* Delegación: botones Eliminar */
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.codelab-remove-tc');
            if (!btn) { return; }
            var row  = btn.closest('.codelab-tc-row');
            if (!row) { return; }
            var rows = container.querySelectorAll('.codelab-tc-row');
            if (rows.length <= 1) {
                clearRow(row);
            } else {
                row.remove();
            }
            reindex(container);
            serialize(container, jsonField);
        });

        /* Serializar en tiempo real */
        container.addEventListener('input',  function() { serialize(container, jsonField); });
        container.addEventListener('change', function() { serialize(container, jsonField); });

        /* Serializar antes de enviar */
        if (form) {
            form.addEventListener('submit', function() { serialize(container, jsonField); });
        }

        /* Serializar estado inicial */
        serialize(container, jsonField);
    }

    /* ── Añadir nueva fila ────────────────────────────────────────────────── */
    function addRow(container) {
        var rows  = container.querySelectorAll('.codelab-tc-row');
        var index = rows.length;

        var div = document.createElement('div');
        div.className    = 'codelab-tc-row row mb-2 align-items-start';
        div.dataset.index = index;
        div.innerHTML    = rowHtml(index, { name:'', stdin:'', expected_output:'', points:'1', is_hidden:false });

        var target = container.querySelector('#codelab-tc-rows') || container;
        target.appendChild(div);
    }

    /* ── HTML de una fila ─────────────────────────────────────────────────── */
    function rowHtml(index, data) {
        var chk = data.is_hidden ? 'checked' : '';
        return '<div class="col-md-3 col-sm-12 mb-1">' +
               '<input type="text" class="form-control form-control-sm tc-name"' +
               ' placeholder="Ej: Prueba básica" value="' + esc(data.name || '') + '"/>' +
               '</div>' +
               '<div class="col-md-3 col-sm-12 mb-1">' +
               '<textarea class="form-control form-control-sm tc-stdin" rows="2"' +
               ' placeholder="Entrada estándar (stdin)">' + esc(data.stdin || '') + '</textarea>' +
               '</div>' +
               '<div class="col-md-3 col-sm-12 mb-1">' +
               '<textarea class="form-control form-control-sm tc-expected" rows="2"' +
               ' placeholder="Salida esperada">' + esc(data.expected_output || '') + '</textarea>' +
               '</div>' +
               '<div class="col-md-1 col-sm-4 mb-1">' +
               '<label class="d-block d-md-none small text-muted">Puntos</label>' +
               '<input type="number" class="form-control form-control-sm tc-points"' +
               ' min="0" step="0.5" value="' + esc(String(data.points || '1')) + '"' +
               ' style="min-width:60px;"/>' +
               '</div>' +
               '<div class="col-md-1 col-sm-4 mb-1 text-center pt-2">' +
               '<label class="d-block d-md-none small text-muted">Oculto</label>' +
               '<input type="checkbox" class="tc-hidden" ' + chk +
               ' title="Ocultar al estudiante" style="width:20px;height:20px;"/>' +
               '</div>' +
               '<div class="col-md-1 col-sm-4 mb-1 pt-1">' +
               '<button type="button" class="btn btn-sm btn-danger codelab-remove-tc" title="Eliminar">' +
               '<i class="fa fa-trash"></i></button>' +
               '</div>';
    }

    /* ── Limpiar fila (cuando es la última) ─────────────────────────────── */
    function clearRow(row) {
        row.querySelectorAll('input[type="text"], textarea').forEach(function(el) { el.value = ''; });
        var num = row.querySelector('input[type="number"]');
        if (num)  { num.value = '1'; }
        var chk = row.querySelector('input[type="checkbox"]');
        if (chk)  { chk.checked = false; }
    }

    /* ── Re-indexar filas ─────────────────────────────────────────────────── */
    function reindex(container) {
        container.querySelectorAll('.codelab-tc-row').forEach(function(row, i) {
            row.dataset.index = i;
        });
    }

    /* ── Serializar a JSON oculto ─────────────────────────────────────────── */
    function serialize(container, jsonField) {
        if (!jsonField) { return; }

        var result = [];
        container.querySelectorAll('.codelab-tc-row').forEach(function(row, i) {
            var nameEl     = row.querySelector('.tc-name');
            var stdinEl    = row.querySelector('.tc-stdin');
            var expectedEl = row.querySelector('.tc-expected');
            var pointsEl   = row.querySelector('.tc-points');
            var hiddenEl   = row.querySelector('.tc-hidden');

            var name     = (nameEl     ? nameEl.value     : '').trim();
            var stdin    =  stdinEl    ? stdinEl.value    : '';
            var expected =  expectedEl ? expectedEl.value : '';
            var pts      = parseFloat(pointsEl ? pointsEl.value : '1');
            var hidden   = (hiddenEl && hiddenEl.checked) ? 1 : 0;

            if (name || expected) {
                result.push({
                    name:            name || ('Caso ' + (i + 1)),
                    stdin:           stdin,
                    expected_output: expected,
                    points:          isNaN(pts) ? 1 : pts,
                    is_hidden:       hidden,
                    ordering:        i
                });
            }
        });

        jsonField.value = JSON.stringify(result);
    }

    function esc(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* Arrancar cuando el DOM esté listo */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
