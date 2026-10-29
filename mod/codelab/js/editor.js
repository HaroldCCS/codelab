/**
 * CodeLab — Editor de código Monaco + lógica de ejecución.
 * Se carga como script normal (no AMD) mediante $PAGE->requires->js().
 * La config la publica view.php en el atributo data-codelab-config del contenedor
 * #codelab-workspace y se lee al arrancar, de modo que no importa el orden de carga.
 */
(function() {
    'use strict';

    var cfg    = {};
    var editor = null;

    /* ── Config del DOM ─────────────────────────────────────────────────────────── */
    function readConfig() {
        var host = document.getElementById('codelab-workspace');
        var raw  = host ? host.getAttribute('data-codelab-config') : null;

        if (raw) {
            try {
                return JSON.parse(raw);
            } catch (e) {
                if (window.console) {
                    window.console.error('CodeLab: configuracion invalida', e);
                }
            }
        }
        /* Respaldo por compatibilidad con versiones anteriores. */
        return window.CODELAB_CFG || {};
    }

    /* ── Arranque ─────────────────────────────────────────────────────────── */
    function boot() {
        cfg = readConfig();
        loadMonaco(function() {
            initEditor();
            renderTestcasesList();
            bindEvents();
        });
    }

    /* ── Carga de Monaco desde CDN ─────────────────────────────────────────── */
    function loadMonaco(cb) {
        if (window.monaco) { cb(); return; }

        var script   = document.createElement('script');
        script.src   = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.44.0/min/vs/loader.js';
        script.onload = function() {
            /* Guardar require de Moodle/RequireJS para no pisarlo */
            var moodleRequire = window.require;

            window.require.config({
                paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.44.0/min/vs' }
            });

            window.require(['vs/editor/editor.main'], function() {
                window.require = moodleRequire;   /* restaurar */
                defineTheme();
                cb();
            });
        };
        script.onerror = function() {
            document.getElementById('codelab-monaco-editor').innerHTML =
                '<div style="color:#f38ba8;padding:16px;">No se pudo cargar Monaco Editor. ' +
                'Verifica tu conexión a internet.</div>';
        };
        document.head.appendChild(script);
    }

    /* ── Inicializar editor Monaco ─────────────────────────────────────────── */
    function initEditor() {
        var container = document.getElementById(cfg.editorEl || 'codelab-monaco-editor');
        if (!container) { return; }

        editor = window.monaco.editor.create(container, {
            value:               cfg.initialCode || defaultCode(cfg.language),
            language:            monacoLang(cfg.language),
            theme:               'codelab-dark',
            automaticLayout:     true,
            minimap:             { enabled: false },
            fontSize:            14,
            lineHeight:          22,
            fontFamily:          "'JetBrains Mono', Consolas, monospace",
            scrollBeyondLastLine: false,
            renderLineHighlight: 'all',
            tabSize:             4,
            insertSpaces:        true,
            padding:             { top: 12, bottom: 12 }
        });

        /* Cambio de lenguaje */
        var sel = document.getElementById('codelab-lang-select');
        if (sel) {
            sel.addEventListener('change', function() {
                window.monaco.editor.setModelLanguage(editor.getModel(), monacoLang(sel.value));
            });
        }
    }

    /* ── Renderizar lista de casos de prueba ───────────────────────────────── */
    function renderTestcasesList() {
        var container = document.getElementById('codelab-testcases-list');
        if (!container) { return; }

        var tcs = cfg.testcases || [];

        if (tcs.length === 0 && !cfg.hiddenCount) {
            container.innerHTML =
                '<div class="codelab-tc-empty text-muted p-3">' +
                '<i class="fa fa-info-circle"></i> Sin casos de prueba visibles.</div>';
            updateBadge(0, 0);
            return;
        }

        var html = '';
        tcs.forEach(function(tc, i) { html += tcCard(tc, i, null); });

        if (cfg.hiddenCount > 0) {
            html += '<div class="codelab-tc-hidden-notice alert alert-secondary mt-2 py-2">' +
                    '<i class="fa fa-eye-slash"></i> <strong>' + cfg.hiddenCount +
                    '</strong> caso(s) ocultos — también se evaluarán.</div>';
        }

        container.innerHTML = html;
        updateBadge(0, tcs.length + (cfg.hiddenCount || 0));
    }

    /* ── Tarjeta de caso de prueba ─────────────────────────────────────────── */
    function tcCard(tc, idx, result) {
        var cls  = 'pending', ico = 'fa-circle-o', txt = 'Sin ejecutar';
        if (result) {
            if (result.passed) {
                cls = 'passed'; ico = 'fa-check-circle'; txt = 'Correcto';
            } else {
                cls = statusCls(result.status);
                ico = 'fa-times-circle';
                txt = statusTxt(result.status);
            }
        }

        var stdinRow = tc.stdin
            ? '<div class="tc-detail"><span class="tc-label">Entrada:</span><code class="tc-code">' +
              esc(tc.stdin) + '</code></div>' : '';

        var expectedRow = tc.expected_output
            ? '<div class="tc-detail"><span class="tc-label">Salida esperada:</span><code class="tc-code">' +
              esc(tc.expected_output) + '</code></div>' : '';

        var actualRow = (result && !result.passed)
            ? '<div class="tc-detail"><span class="tc-label text-danger">Tu salida:</span>' +
              '<code class="tc-code text-danger">' + esc(result.actual_output || '(vacío)') + '</code></div>' : '';

        var errRow = (result && result.stderr)
            ? '<div class="tc-detail"><span class="tc-label text-warning">Error:</span>' +
              '<pre class="tc-code text-warning">' + esc(result.stderr) + '</pre></div>' : '';

        var compRow = (result && result.compile_output)
            ? '<div class="tc-detail"><span class="tc-label text-danger">Error compilación:</span>' +
              '<pre class="tc-code text-danger">' + esc(result.compile_output) + '</pre></div>' : '';

        var ptsLabel  = result ? (result.points_earned + '/' + result.points_possible) : tc.points;
        var ptsBadge  = (result && result.passed) ? 'badge-success' : 'badge-secondary';
        var timeLabel = (result && result.execution_time)
            ? '<span class="tc-meta text-muted ml-2"><i class="fa fa-clock-o"></i> ' +
              result.execution_time + 's</span>' : '';

        return '<div class="codelab-tc-card status-' + cls + '" id="tc-card-' + tc.id + '" data-tcid="' + tc.id + '">' +
               '<div class="tc-card-header d-flex justify-content-between align-items-center">' +
               '<div class="tc-title"><i class="fa ' + ico + ' tc-status-icon"></i>' +
               '<strong class="tc-name">' + esc(tc.name) + '</strong>' +
               '<span class="tc-status-text text-muted ml-1">' + txt + '</span>' + timeLabel + '</div>' +
               '<span class="tc-points badge ' + ptsBadge + '">' + ptsLabel + ' pts</span>' +
               '</div>' +
               '<div class="tc-card-body" style="display:none;">' +
               stdinRow + expectedRow + actualRow + errRow + compRow +
               '</div></div>';
    }

    /* ── Vincular botones ──────────────────────────────────────────────────── */
    function bindEvents() {
        var runBtn = document.getElementById('codelab-run-btn');
        if (runBtn) {
            runBtn.addEventListener('click', function() { runCode('run'); });
        }

        var subBtn = document.getElementById('codelab-submit-btn');
        if (subBtn) {
            subBtn.addEventListener('click', function() {
                if (window.confirm('¿Seguro que deseas entregar? Contará como un intento.')) {
                    runCode('submit');
                }
            });
        }

        /* Expandir / colapsar cuerpo de tarjetas */
        document.addEventListener('click', function(e) {
            var hdr = e.target.closest('.tc-card-header');
            if (hdr) {
                var body = hdr.closest('.codelab-tc-card').querySelector('.tc-card-body');
                if (body) { body.style.display = body.style.display === 'none' ? 'block' : 'none'; }
            }
        });
    }

    /* ── Ejecutar código ───────────────────────────────────────────────────── */
    function runCode(action) {
        if (!editor) { return; }

        var code     = editor.getValue();
        var sel      = document.getElementById('codelab-lang-select');
        var language = sel ? sel.value : cfg.language;
        var outputEl = document.getElementById('codelab-output');

        if (!code.trim()) {
            setOutput('El editor está vacío. Escribe tu código.', 'warning');
            return;
        }

        setBusy(true, action);
        if (outputEl) {
            outputEl.textContent = action === 'submit'
                ? '⏳ Enviando entrega...' : '⏳ Ejecutando código...';
        }

        fetch(cfg.wwwroot + 'mod/codelab/execute.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                cmid:     cfg.cmid,
                code:     code,
                language: language,
                action:   action,
                sesskey:  cfg.sesskey
            })
        })
        .then(function(r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.json();
        })
        .then(function(data) {
            if (!data.success) { setOutput('Error: ' + (data.error || 'desconocido'), 'danger'); return; }
            showResults(data, action);
        })
        .catch(function(err) {
            setOutput('Error de conexión: ' + err.message, 'danger');
        })
        .finally(function() { setBusy(false, action); });
    }

    /* ── Mostrar resultados ────────────────────────────────────────────────── */
    function showResults(data, action) {
        var container = document.getElementById('codelab-testcases-list');
        if (!container) { return; }

        var map = {};
        (data.results || []).forEach(function(r) { map[r.testcase_id] = r; });

        var html = '';
        (cfg.testcases || []).forEach(function(tc, i) {
            html += tcCard(tc, i, map[tc.id] || null);
        });

        if (cfg.hiddenCount > 0) {
            var hiddenPassed = (data.results || []).filter(function(r) { return r.hidden && r.passed; }).length;
            var hCls = hiddenPassed === cfg.hiddenCount ? 'alert-success' : 'alert-secondary';
            var hIco = hiddenPassed === cfg.hiddenCount ? 'fa-check' : 'fa-eye-slash';
            html += '<div class="codelab-tc-hidden-notice alert ' + hCls + ' mt-2 py-2">' +
                    '<i class="fa ' + hIco + '"></i> Casos ocultos: <strong>' +
                    hiddenPassed + '/' + cfg.hiddenCount + '</strong> correctos.</div>';
        }

        container.innerHTML = html;
        updateBadge(data.tests_passed, data.tests_total);

        var pct = data.tests_total > 0
            ? Math.round(data.tests_passed / data.tests_total * 100) : 0;
        var icon = pct === 100 ? '🎉' : pct >= 60 ? '📊' : '❌';
        setOutput(icon + ' ' + data.tests_passed + '/' + data.tests_total +
                  ' correctos  |  Nota: ' + data.grade + ' / ' + data.max_grade, '');

        /* Mostrar primer error si existe */
        var firstErr = (data.results || []).filter(function(r) {
            return r.compile_output || r.stderr;
        })[0];
        var outEl = document.getElementById('codelab-output');
        if (firstErr && outEl) {
            outEl.textContent += '\n⚠ ' + String(firstErr.compile_output || firstErr.stderr).substring(0, 300);
        }

        if (action === 'submit' && data.submitted) {
            showAiFeedback(data.ai_feedback);
            showSuccessModal(data.grade, data.max_grade, data.tests_passed, data.tests_total, pct);
        }

        /* Expandir tarjetas fallidas automáticamente */
        setTimeout(function() {
            document.querySelectorAll('.codelab-tc-card.status-failed, .codelab-tc-card.status-error')
                .forEach(function(c) {
                    var b = c.querySelector('.tc-card-body');
                    if (b) { b.style.display = 'block'; }
                });
        }, 120);
    }

    /* ── Helpers UI ────────────────────────────────────────────────────────── */
    function updateBadge(passed, total) {
        var b = document.getElementById('codelab-score-badge');
        if (!b) { return; }
        b.textContent = passed + ' / ' + total;
        b.className = 'badge ml-2 ' +
            (total === 0 || passed === 0 ? 'badge-secondary'
           : passed === total            ? 'badge-success'
                                         : 'badge-warning');
    }

    function setBusy(on, action) {
        var rb = document.getElementById('codelab-run-btn');
        var sb = document.getElementById('codelab-submit-btn');
        if (rb) {
            rb.disabled  = on;
            rb.innerHTML = (on && action === 'run')
                ? '<i class="fa fa-spinner fa-spin"></i> Ejecutando...'
                : '<i class="fa fa-play"></i> Ejecutar y probar';
        }
        if (sb) {
            sb.disabled  = on;
            sb.innerHTML = (on && action === 'submit')
                ? '<i class="fa fa-spinner fa-spin"></i> Enviando...'
                : '<i class="fa fa-paper-plane"></i> Entregar';
        }
    }

    function setOutput(msg, type) {
        var el = document.getElementById('codelab-output');
        if (!el) { return; }
        el.className = 'codelab-output-pre' + (type ? ' text-' + type : '');
        el.textContent = msg;
    }

    /* ── Recomendaciones IA ────────────────────────────────────────────────── */
    function showAiFeedback(text) {
        var section = document.getElementById('codelab-ai-section');
        var body    = document.getElementById('codelab-ai-feedback');
        if (!section || !body) { return; }

        if (!text || !String(text).trim()) {
            section.style.display = 'none';
            return;
        }

        body.innerHTML = renderMarkdown(String(text));
        section.style.display = 'block';
        section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* Mini-render Markdown seguro: escapa primero y solo interpreta un subconjunto
       (negritas, código en línea y viñetas). Suficiente para la salida de la IA. */
    function renderMarkdown(md) {
        var lines = esc(md).split('\n');
        var html = '', inList = false;

        function inline(s) {
            return s
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/`([^`]+)`/g, '<code>$1</code>');
        }

        lines.forEach(function(line) {
            var trimmed = line.trim();
            var bullet = trimmed.match(/^[-*]\s+(.*)$/);
            if (bullet) {
                if (!inList) { html += '<ul>'; inList = true; }
                html += '<li>' + inline(bullet[1]) + '</li>';
            } else {
                if (inList) { html += '</ul>'; inList = false; }
                if (trimmed) { html += '<p>' + inline(trimmed) + '</p>'; }
            }
        });
        if (inList) { html += '</ul>'; }
        return html;
    }

    function showSuccessModal(grade, maxGrade, passed, total, pct) {
        /* ── Mensaje y emoji según nota ── */
        var emoji, title, subtitle, gradeCls;
        if (pct === 100) {
            emoji = '🏆'; title = '¡Perfecto!';
            subtitle = '¡Todos los casos correctos! Eres increíble.';
            gradeCls = '#22c55e';
        } else if (pct >= 80) {
            emoji = '🎉'; title = '¡Excelente trabajo!';
            subtitle = 'Casi perfecto. ¡Sigue así!';
            gradeCls = '#22c55e';
        } else if (pct >= 60) {
            emoji = '👍'; title = '¡Buen trabajo!';
            subtitle = 'Tu entrega fue registrada. Puedes seguir mejorando.';
            gradeCls = '#f59e0b';
        } else if (pct > 0) {
            emoji = '💪'; title = '¡Entrega registrada!';
            subtitle = 'Revisa los casos fallados y sigue practicando.';
            gradeCls = '#f97316';
        } else {
            emoji = '📬'; title = '¡Entrega registrada!';
            subtitle = 'No te rindas, revisa los errores e inténtalo de nuevo.';
            gradeCls = '#ef4444';
        }

        /* ── Estilos inyectados una sola vez ── */
        if (!document.getElementById('cl-modal-styles')) {
            var st = document.createElement('style');
            st.id = 'cl-modal-styles';
            st.textContent = [
                '@keyframes cl-fadeIn{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}',
                '@keyframes cl-pop{0%{transform:scale(0)}70%{transform:scale(1.15)}100%{transform:scale(1)}}',
                '@keyframes cl-confetti-fall{0%{transform:translateY(-10px) rotate(0deg);opacity:1}',
                '100%{transform:translateY(100vh) rotate(720deg);opacity:0}}',
                '#cl-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;',
                'display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px)}',
                '#cl-modal-box{background:#1e1e2e;border-radius:20px;padding:40px 48px;max-width:480px;',
                'width:90%;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,.6);',
                'animation:cl-fadeIn .45s cubic-bezier(.22,1,.36,1) forwards;position:relative;overflow:hidden}',
                '#cl-modal-box::before{content:"";position:absolute;inset:0;border-radius:20px;',
                'background:linear-gradient(135deg,rgba(139,92,246,.15),rgba(59,130,246,.1));pointer-events:none}',
                '.cl-emoji{font-size:72px;animation:cl-pop .5s .2s cubic-bezier(.34,1.56,.64,1) both}',
                '.cl-title{color:#cdd6f4;font-size:1.9rem;font-weight:700;margin:.5rem 0 .25rem}',
                '.cl-subtitle{color:#a6adc8;font-size:1rem;margin-bottom:1.5rem}',
                '.cl-grade-ring{width:130px;height:130px;border-radius:50%;margin:0 auto 1.2rem;',
                'display:flex;flex-direction:column;align-items:center;justify-content:center;',
                'border:6px solid;box-shadow:0 0 32px rgba(0,0,0,.4)}',
                '.cl-grade-num{font-size:2rem;font-weight:800;line-height:1}',
                '.cl-grade-max{font-size:.85rem;opacity:.75;margin-top:2px}',
                '.cl-tests{color:#a6adc8;font-size:.9rem;margin-bottom:1.5rem}',
                '.cl-close-btn{background:linear-gradient(135deg,#8b5cf6,#3b82f6);color:#fff;',
                'border:none;border-radius:50px;padding:12px 36px;font-size:1rem;font-weight:600;',
                'cursor:pointer;transition:transform .15s,box-shadow .15s;letter-spacing:.02em}',
                '.cl-close-btn:hover{transform:scale(1.05);box-shadow:0 8px 24px rgba(139,92,246,.45)}',
                '.cl-confetti{position:absolute;width:10px;height:10px;border-radius:2px;',
                'animation:cl-confetti-fall linear forwards}'
            ].join('');
            document.head.appendChild(st);
        }

        /* ── Overlay y caja ── */
        var overlay = document.createElement('div');
        overlay.id  = 'cl-modal-overlay';

        overlay.innerHTML =
            '<div id="cl-modal-box">' +
            '<div class="cl-emoji">' + emoji + '</div>' +
            '<div class="cl-title">' + title + '</div>' +
            '<div class="cl-subtitle">' + subtitle + '</div>' +
            '<div class="cl-grade-ring" style="border-color:' + gradeCls + ';color:' + gradeCls + ';">' +
            '<span class="cl-grade-num">' + grade + '</span>' +
            '<span class="cl-grade-max">/ ' + maxGrade + ' pts</span>' +
            '</div>' +
            '<div class="cl-tests">✔ ' + passed + ' de ' + total + ' casos correctos</div>' +
            '<button class="cl-close-btn" id="cl-close-btn">¡Entendido!</button>' +
            '</div>';

        document.body.appendChild(overlay);

        /* ── Confeti ── */
        var colors = ['#f43f5e','#f59e0b','#22c55e','#3b82f6','#8b5cf6','#ec4899','#06b6d4'];
        var box    = overlay.querySelector('#cl-modal-box');
        for (var i = 0; i < 55; i++) {
            (function(idx) {
                setTimeout(function() {
                    var c   = document.createElement('div');
                    c.className = 'cl-confetti';
                    var size = 6 + Math.random() * 8;
                    c.style.cssText = [
                        'left:'            + (Math.random() * 100) + '%',
                        'top:-12px',
                        'width:'           + size + 'px',
                        'height:'          + size + 'px',
                        'background:'      + colors[idx % colors.length],
                        'animation-duration:' + (1.2 + Math.random() * 1.8) + 's',
                        'animation-delay:0s',
                        'border-radius:'   + (Math.random() > .5 ? '50%' : '2px')
                    ].join(';');
                    box.appendChild(c);
                    setTimeout(function() { c.remove(); }, 3200);
                }, idx * 40);
            })(i);
        }

        /* ── Cierre ── */
        function closeModal() { overlay.remove(); }
        document.getElementById('cl-close-btn').addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) { closeModal(); }
        });
        document.addEventListener('keydown', function onKey(e) {
            if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', onKey); }
        });
    }

    /* ── Tema Monaco ───────────────────────────────────────────────────────── */
    function defineTheme() {
        window.monaco.editor.defineTheme('codelab-dark', {
            base: 'vs-dark', inherit: true,
            rules: [
                { token: 'comment', foreground: '6A9955', fontStyle: 'italic' },
                { token: 'keyword', foreground: '569CD6' },
                { token: 'string',  foreground: 'CE9178' },
                { token: 'number',  foreground: 'B5CEA8' }
            ],
            colors: {
                'editor.background':                '#1E1E2E',
                'editor.foreground':                '#CDD6F4',
                'editor.lineHighlightBackground':   '#2A2A3E',
                'editorCursor.foreground':          '#F5C2E7',
                'editor.selectionBackground':       '#3D3D5C',
                'editorLineNumber.foreground':      '#45475A',
                'editorLineNumber.activeForeground':'#CDD6F4'
            }
        });
    }

    /* ── Utilidades ────────────────────────────────────────────────────────── */
    function monacoLang(l) {
        return { python:'python', javascript:'javascript', java:'java', c:'c',
                 cpp:'cpp', php:'php', csharp:'csharp', ruby:'ruby',
                 go:'go', kotlin:'kotlin' }[l] || 'plaintext';
    }

    function defaultCode(l) {
        var d = {
            python:     '# Escribe tu solución aquí\n',
            javascript: '// Escribe tu solución aquí\n',
            java:       'public class Main {\n    public static void main(String[] args) {\n        // Tu código\n    }\n}\n',
            c:          '#include <stdio.h>\nint main() {\n    // Tu código\n    return 0;\n}\n',
            cpp:        '#include <cstdio>\nint main() {\n    // Tu código (usa scanf/printf)\n    return 0;\n}\n',
            php:        '<?php\n// Tu código\n',
            csharp:     'using System;\nclass Program {\n    static void Main() {\n        // Tu código\n    }\n}\n',
            ruby:       '# Escribe tu solución aquí\n',
            go:         'package main\nimport "fmt"\nfunc main() {\n    // Tu código\n}\n',
            kotlin:     'fun main() {\n    // Tu código\n}\n'
        };
        return d[l] || '// Escribe tu solución\n';
    }

    function statusCls(s) {
        return { passed:'passed', failed:'failed', timeout:'timeout',
                 compile_error:'error', error:'error' }[s] || 'error';
    }

    function statusTxt(s) {
        return { passed:'Correcto', failed:'Incorrecto', timeout:'Tiempo excedido',
                 compile_error:'Error de compilación', error:'Error de ejecución',
                 internal_error:'Error interno' }[s] || 'Error';
    }

    function esc(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Arrancar cuando el DOM esté listo ────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

}());
