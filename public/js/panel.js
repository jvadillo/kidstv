/* KidsTV · comportamiento del panel de adultos (sin dependencias). */
(function () {
    'use strict';

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    function onReady(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function formatBytes(bytes) {
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return bytes.toLocaleString('es-ES', { maximumFractionDigits: i >= 2 ? 1 : 0 }) + ' ' + units[i];
    }

    function firstError(data, fallback) {
        if (data && data.errors) {
            for (var key in data.errors) {
                if (data.errors[key] && data.errors[key][0]) return data.errors[key][0];
            }
        }
        return (data && (data.error || data.message)) || fallback;
    }

    function requestJson(method, url, body, headers) {
        var opts = {
            method: method,
            credentials: 'same-origin',
            headers: Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, headers || {}),
            body: body
        };
        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                return { ok: res.ok, status: res.status, data: data };
            });
        });
    }

    // Confirmación antes de acciones destructivas.
    function initConfirms() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (form.dataset && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    }

    // Menús desplegables (<details data-menu>): se cierran al pulsar fuera o con Escape.
    function initMenus() {
        function openMenus() { return document.querySelectorAll('details[data-menu][open]'); }

        document.addEventListener('click', function (e) {
            openMenus().forEach(function (menu) {
                if (!menu.contains(e.target)) menu.open = false;
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            openMenus().forEach(function (menu) {
                menu.open = false;
                menu.querySelector('summary').focus();
            });
        });
        // «toggle» no burbujea: se escucha en captura. Al abrir un menú, se mete en pantalla y,
        // si lleva formulario, foco al campo.
        document.addEventListener('toggle', function (e) {
            var menu = e.target;
            if (!menu.open || !menu.matches || !menu.matches('details[data-menu]')) return;
            fitMenu(menu);
            var input = menu.querySelector('input[type=text]');
            if (input) input.focus();
        }, true);
        window.addEventListener('resize', function () { openMenus().forEach(fitMenu); });
    }

    // Desplaza el panel lo justo para que no se salga por los lados (p. ej. en el móvil,
    // cuando los botones bajan a otra línea y el panel alineado a la derecha asoma por la izquierda).
    function fitMenu(menu) {
        var panel = menu.querySelector('.menu-panel');
        if (!panel) return;
        panel.style.transform = '';
        var gutter = 8;
        var viewport = document.documentElement.clientWidth;
        var rect = panel.getBoundingClientRect();
        var shift = 0;
        if (rect.left < gutter) shift = gutter - rect.left;
        else if (rect.right > viewport - gutter) shift = Math.max(gutter - rect.left, viewport - gutter - rect.right);
        if (shift) panel.style.transform = 'translateX(' + Math.round(shift) + 'px)';
    }

    // Marcar todos / ninguno en listas de casillas.
    function initCheckTools() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-check-all],[data-check-none]');
            if (!btn) return;
            var name = btn.dataset.checkAll || btn.dataset.checkNone;
            var checked = !!btn.dataset.checkAll;
            btn.closest('form').querySelectorAll('input[name="' + name + '"]').forEach(function (cb) { cb.checked = checked; });
        });
    }

    // Límite diario: el control deslizante va por posiciones; se muestran los minutos que corresponden.
    function formatMinutes(minutes) {
        var hours = Math.floor(minutes / 60);
        var rest = minutes % 60;
        if (!hours) return rest + ' min';
        return rest ? hours + ' h ' + rest + ' min' : hours + ' h';
    }

    function initDailyLimit() {
        var range = document.getElementById('daily_limit_step');
        if (!range) return;
        var minutes = JSON.parse(range.dataset.minutes);
        var output = document.querySelector('.daily-limit-value');
        range.addEventListener('input', function () {
            var label = formatMinutes(minutes[range.value]);
            output.textContent = label;
            range.setAttribute('aria-valuetext', label);
        });
    }

    // Comprobación del enlace de YouTube mientras se escribe/pega.
    function initYouTubeForm() {
        var form = document.querySelector('[data-youtube-form]');
        if (!form) return;

        var input = form.querySelector('[data-youtube-url]');
        var preview = form.querySelector('[data-youtube-preview]');
        var errorBox = form.querySelector('[data-youtube-error]');
        var title = form.querySelector('#title');
        var timer = null;
        var last = '';

        function check() {
            var value = input.value.trim();
            if (!value || value === last) return;
            last = value;
            errorBox.hidden = true;

            var body = new FormData();
            body.append('url', value);
            requestJson('POST', form.dataset.previewUrl, body).then(function (res) {
                if (value !== input.value.trim()) return;
                if (!res.ok) {
                    preview.hidden = true;
                    errorBox.textContent = firstError(res.data, 'No se ha podido comprobar el enlace.');
                    errorBox.hidden = false;
                    return;
                }
                var d = res.data;
                preview.querySelector('[data-youtube-thumb]').src = d.thumbnail;
                preview.querySelector('[data-youtube-title]').textContent = d.title || 'Vídeo de YouTube';
                preview.querySelector('[data-youtube-author]').textContent = d.author || (d.checked ? '' : 'No se pudo comprobar con YouTube; se intentará reproducir igualmente.');
                preview.hidden = false;
                if (title && !title.value && d.title) title.value = d.title;
            }).catch(function () {
                errorBox.textContent = 'Sin conexión. Comprueba tu red e inténtalo de nuevo.';
                errorBox.hidden = false;
            });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(check, 500);
        });
        input.addEventListener('change', check);
        if (input.value) check();
    }

    // Subida por fragmentos con progreso, reintentos y cancelación.
    function initUploadForm() {
        var form = document.querySelector('[data-upload-form]');
        if (!form) return;

        var fileInput = form.querySelector('[data-upload-file]');
        var errorBox = form.querySelector('[data-upload-error]');
        var progress = form.querySelector('[data-upload-progress]');
        var bar = form.querySelector('[data-upload-bar]');
        var status = form.querySelector('[data-upload-status]');
        var submit = form.querySelector('[data-upload-submit]');
        var cancelBtn = form.querySelector('[data-upload-cancel]');
        var backLink = form.querySelector('[data-upload-back]');
        var titleInput = form.querySelector('#title');
        var maxBytes = parseInt(form.dataset.maxBytes, 10);
        var extensions = form.dataset.extensions.split(',');
        var cancelled = false;
        var uploading = false;
        var current = null;

        function showError(message) {
            errorBox.textContent = message;
            errorBox.hidden = false;
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function setBusy(busy) {
            uploading = busy;
            submit.disabled = busy;
            cancelBtn.hidden = !busy;
            backLink.hidden = busy;
            fileInput.disabled = busy;
        }

        fileInput.addEventListener('change', function () {
            errorBox.hidden = true;
            var file = fileInput.files[0];
            if (file && titleInput && !titleInput.value) {
                titleInput.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').trim().slice(0, 120);
            }
        });

        window.addEventListener('beforeunload', function (e) {
            if (uploading) { e.preventDefault(); e.returnValue = ''; }
        });

        cancelBtn.addEventListener('click', function () {
            cancelled = true;
            if (current) {
                requestJson('DELETE', current.chunk_url.replace(/\/fragmento$/, ''));
            }
        });

        function sendChunk(url, file, offset, size, attempt) {
            var chunk = file.slice(offset, offset + size);
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Upload-Offset': String(offset), 'Content-Type': 'application/octet-stream' },
                body: chunk
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (res.ok) return data.received;
                    if (res.status === 409 && typeof data.received === 'number') return data.received;
                    throw new Error(firstError(data, 'Error del servidor (' + res.status + ').'));
                });
            }).catch(function (err) {
                if (cancelled || attempt >= 4) throw err;
                status.textContent = 'Conexión inestable, reintentando…';
                return new Promise(function (resolve) { setTimeout(resolve, 1500 * (attempt + 1)); })
                    .then(function () { return sendChunk(url, file, offset, size, attempt + 1); });
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (uploading) return;
            errorBox.hidden = true;

            var file = fileInput.files[0];
            if (!file) return showError('Elige un archivo para subir.');
            var ext = (file.name.split('.').pop() || '').toLowerCase();
            if (extensions.indexOf(ext) === -1) return showError('Formato no admitido. Usa ' + extensions.join(', ').toUpperCase() + '.');
            if (file.size > maxBytes) return showError('El archivo ocupa ' + formatBytes(file.size) + ' y el máximo es ' + formatBytes(maxBytes) + '.');
            if (titleInput && !titleInput.value.trim()) return showError('Escribe un título.');

            cancelled = false;
            setBusy(true);
            progress.hidden = false;
            bar.style.width = '0%';
            status.textContent = 'Preparando la subida…';

            var startBody = new FormData();
            startBody.append('kind', form.dataset.kind);
            startBody.append('name', file.name);
            startBody.append('size', String(file.size));

            requestJson('POST', form.dataset.startUrl, startBody).then(function (res) {
                if (!res.ok) throw new Error(firstError(res.data, 'No se ha podido iniciar la subida.'));
                current = res.data;
                var offset = 0;
                var started = Date.now();

                function next() {
                    if (cancelled) throw new Error('cancelled');
                    if (offset >= file.size) return Promise.resolve();
                    return sendChunk(current.chunk_url, file, offset, current.chunk_size, 0).then(function (received) {
                        offset = received;
                        var pct = Math.floor(offset / file.size * 100);
                        bar.style.width = pct + '%';
                        var elapsed = (Date.now() - started) / 1000;
                        var speed = elapsed > 0 ? offset / elapsed : 0;
                        var left = speed > 0 ? Math.ceil((file.size - offset) / speed) : 0;
                        status.textContent = 'Subiendo… ' + pct + '% (' + formatBytes(offset) + ' de ' + formatBytes(file.size) + ')' +
                            (left > 5 ? ' · quedan unos ' + (left > 90 ? Math.ceil(left / 60) + ' min' : left + ' s') : '');
                        return next();
                    });
                }
                return next();
            }).then(function () {
                status.textContent = 'Guardando…';
                var body = new FormData(form);
                return requestJson('POST', current.complete_url, body);
            }).then(function (res) {
                if (!res.ok) throw new Error(firstError(res.data, 'No se ha podido guardar el contenido.'));
                uploading = false;
                bar.style.width = '100%';
                status.textContent = '¡Subido!';
                window.location.href = res.data.redirect;
            }).catch(function (err) {
                setBusy(false);
                if (err.message === 'cancelled') {
                    progress.hidden = true;
                    status.textContent = '';
                    return;
                }
                showError(err.message || 'Error inesperado durante la subida.');
            });
        });
    }

    // Buscador de YouTube: vista previa en ventana y «Añadir» sin salir de los resultados.
    function initYouTubeSearch() {
        var dialog = document.querySelector('[data-yt-dialog]');
        if (dialog && typeof dialog.showModal === 'function') {
            var frame = dialog.querySelector('iframe');
            var titleEl = dialog.querySelector('[data-yt-dialog-title]');
            document.addEventListener('click', function (e) {
                var thumb = e.target.closest('[data-preview]');
                if (!thumb) return;
                titleEl.textContent = thumb.dataset.title || '';
                frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(thumb.dataset.preview) + '?autoplay=1&rel=0';
                dialog.showModal();
            });
            dialog.querySelector('[data-yt-dialog-close]').addEventListener('click', function () { dialog.close(); });
            dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
            dialog.addEventListener('close', function () { frame.src = 'about:blank'; });
        } else {
            // Sin <dialog>: la miniatura abre el vídeo en YouTube.
            document.addEventListener('click', function (e) {
                var thumb = e.target.closest('[data-preview]');
                if (thumb) window.open('https://www.youtube.com/watch?v=' + encodeURIComponent(thumb.dataset.preview), '_blank', 'noopener');
            });
        }

        var form = document.querySelector('[data-yt-add-form]');
        if (!form) return;
        var errorBox = form.querySelector('[data-yt-add-error]');

        form.addEventListener('submit', function (e) {
            var button = e.submitter;
            if (!button || !button.value) return;
            e.preventDefault();
            errorBox.hidden = true;

            var body = new FormData(form);
            body.set('url', button.value);
            var original = button.innerHTML;
            button.disabled = true;
            button.textContent = 'Añadiendo…';

            requestJson('POST', form.action, body).then(function (res) {
                if (!res.ok) throw new Error(firstError(res.data, 'No se ha podido añadir el vídeo.'));
                var done = document.createElement('span');
                done.className = 'btn btn-sm btn-added';
                done.setAttribute('aria-disabled', 'true');
                done.textContent = '✓ Añadido';
                button.replaceWith(done);
                showFlash(res.data.message);
            }).catch(function (err) {
                button.disabled = false;
                button.innerHTML = original;
                errorBox.textContent = err.message;
                errorBox.hidden = false;
                button.closest('.yt-result').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    }

    // Aviso flotante y breve (el formulario de resultados no recarga la página).
    function showFlash(message) {
        if (!message) return;
        var flash = document.querySelector('.flash-floating');
        if (!flash) {
            flash = document.createElement('div');
            flash.className = 'flash flash-success flash-floating';
            flash.setAttribute('role', 'status');
            document.body.appendChild(flash);
        }
        flash.textContent = message;
        clearTimeout(showFlash.timer);
        flash.classList.add('visible');
        showFlash.timer = setTimeout(function () { flash.classList.remove('visible'); }, 3500);
    }

    onReady(function () {
        initConfirms();
        initMenus();
        initCheckTools();
        initYouTubeForm();
        initUploadForm();
        initYouTubeSearch();
        initDailyLimit();
    });
})();
