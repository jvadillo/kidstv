/* NokoPlay · comportamiento del panel de adultos (sin dependencias). */
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
        document.querySelectorAll('.daily-limit-slider').forEach(function (slider) {
            var range = slider.querySelector('input[type=range]');
            var minutes = JSON.parse(range.dataset.minutes);
            var output = slider.querySelector('.daily-limit-value');
            range.addEventListener('input', function () {
                var label = formatMinutes(minutes[range.value]);
                output.textContent = label;
                range.setAttribute('aria-valuetext', label);
            });
        });
    }

    // Actividad: el aviso «Viendo ahora» se actualiza cada minuto mientras la página está a la vista.
    function initWatching() {
        if (!document.querySelector('[data-watching]')) return;
        setInterval(function () {
            if (document.hidden) return;
            fetch(location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (res) { return res.ok ? res.text() : null; })
                .then(function (html) {
                    if (!html) return;
                    var fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-watching]');
                    var current = document.querySelector('[data-watching]');
                    if (fresh && current) current.replaceWith(fresh);
                })
                .catch(function () {});
        }, 60000);
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

    // Primeros pasos, catálogo: filtros por edad e idioma y selección de series sin recargar.
    function initOnboardingCatalog() {
        var form = document.querySelector('[data-ob-catalog]');
        if (!form) return;
        var cards = form.querySelectorAll('.ob-card');
        var empty = form.querySelector('[data-ob-empty]');
        var count = form.querySelector('[data-ob-count]');
        var stack = form.querySelector('[data-ob-stack]');
        var next = form.querySelector('[data-ob-next]');
        var filters = {};
        form.querySelectorAll('[data-ob-filter][aria-current]').forEach(function (chip) {
            filters[chip.dataset.obFilter] = chip.dataset.value;
        });

        function has(list, value) { return !value || list.split(' ').indexOf(value) !== -1; }

        function applyFilters() {
            var visible = 0;
            cards.forEach(function (card) {
                card.hidden = !(has(card.dataset.ages, filters.edad) && has(card.dataset.langs, filters.idioma));
                if (!card.hidden) visible++;
            });
            empty.hidden = visible > 0;
            form.querySelectorAll('[data-ob-filter]').forEach(function (chip) {
                if ((filters[chip.dataset.obFilter] || '') === chip.dataset.value) chip.setAttribute('aria-current', 'true');
                else chip.removeAttribute('aria-current');
            });
            // La dirección conserva los filtros por si se recarga o se vuelve atrás.
            var params = new URLSearchParams(location.search);
            Object.keys(filters).forEach(function (key) { params.set(key, filters[key]); });
            history.replaceState(null, '', location.pathname + '?' + params.toString());
        }

        function updateSelection() {
            var checked = form.querySelectorAll('.ob-card input:checked');
            var n = checked.length;
            count.textContent = n === 0 ? count.dataset.none : (n === 1 ? count.dataset.one : count.dataset.many.replace(':count', n));
            next.disabled = n === 0;
            // Las portadas de las últimas elegidas, apiladas junto al contador.
            stack.innerHTML = '';
            Array.prototype.slice.call(checked, -4).forEach(function (input) {
                var thumb = input.closest('.ob-card').querySelector('[data-ob-thumb]');
                if (!thumb) return;
                var img = document.createElement('img');
                img.src = thumb.src;
                img.alt = '';
                stack.appendChild(img);
            });
        }

        form.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-ob-filter]');
            if (chip) {
                e.preventDefault();
                filters[chip.dataset.obFilter] = chip.dataset.value;
                applyFilters();
            } else if (e.target.closest('[data-ob-clear]')) {
                e.preventDefault();
                filters = { edad: '', idioma: '' };
                applyFilters();
            }
        });
        form.addEventListener('change', function (e) {
            if (e.target.closest('.ob-card')) updateSelection();
        });
        updateSelection();
    }

    // Primeros pasos, vista previa: el foco recorre las tarjetas como si alguien usara el mando.
    function initTvPreview() {
        var tv = document.querySelector('[data-tv-preview]');
        if (!tv || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
        // Las tres primeras tarjetas de las dos primeras filas: siempre caben en la pantalla.
        var path = [];
        Array.prototype.slice.call(tv.querySelectorAll('.tvp-track'), 0, 2).forEach(function (track) {
            Array.prototype.slice.call(track.children, 0, 3).forEach(function (card) { path.push(card); });
        });
        if (path.length < 2) return;

        var step = 0;
        function tick() {
            path[step].classList.remove('focused');
            step = (step + 1) % path.length;
            path[step].classList.add('focused');
            setTimeout(tick, 1500);
        }
        setTimeout(tick, 2200);
    }

    // Código de 6 cifras: se envía solo al completarlo (también al pegarlo o autocompletarlo).
    function initCodeAutosubmit() {
        var input = document.querySelector('[data-code-input]');
        if (!input) return;
        input.addEventListener('input', function () {
            if (input.value.replace(/\D/g, '').length !== 6 || input.form.dataset.sent) return;
            input.form.dataset.sent = '1';
            if (input.form.requestSubmit) input.form.requestSubmit();
            else input.form.submit();
        });
    }

    // El asa admite ratón/táctil; su menú conserva formularios para teclado y sin JS.
    function initListSorting() {
        var list = document.querySelector('[data-sortable-lists]');
        if (!list) return;
        var status = document.querySelector('[data-sort-status]');
        var busy = false;
        list.querySelectorAll('.drag-handle').forEach(function (handle) {
            var drag = null;
            var suppressClick = false;
            handle.addEventListener('click', function (event) {
                if (suppressClick || busy) { event.preventDefault(); suppressClick = false; }
            });
            handle.addEventListener('pointerdown', function (event) {
                if (busy || event.button !== 0) return;
                var items = Array.from(list.querySelectorAll('[data-list-id]'));
                drag = { y: event.clientY, items: items, from: items.indexOf(handle.closest('[data-list-id]')), to: -1, moved: false };
                handle.setPointerCapture(event.pointerId);
            });
            handle.addEventListener('pointermove', function (event) {
                if (!drag) return;
                if (Math.abs(event.clientY - drag.y) < 8 && !drag.moved) return;
                drag.moved = true;
                handle.closest('details').open = false;
                drag.items[drag.from].classList.add('is-dragging');
                drag.to = drag.items.reduce(function (nearest, item, index) {
                    var rect = item.getBoundingClientRect();
                    var current = drag.items[nearest].getBoundingClientRect();
                    return Math.abs(event.clientY - (rect.top + rect.height / 2)) < Math.abs(event.clientY - (current.top + current.height / 2)) ? index : nearest;
                }, drag.from);
                drag.items.forEach(function (item, index) { item.classList.toggle('drop-target', index === drag.to && index !== drag.from); });
                if (event.clientY < 80) window.scrollBy(0, -16);
                if (event.clientY > window.innerHeight - 150) window.scrollBy(0, 16);
            });
            function cancel() {
                if (!drag) return;
                drag.items.forEach(function (item) { item.classList.remove('is-dragging', 'drop-target'); });
                drag = null;
            }
            handle.addEventListener('pointercancel', cancel);
            handle.addEventListener('keydown', function (event) { if (event.key === 'Escape') cancel(); });
            handle.addEventListener('pointerup', async function () {
                if (!drag) return;
                var move = drag;
                suppressClick = move.moved;
                cancel();
                if (!move.moved || move.to === move.from || move.to < 0) return;
                busy = true;
                list.setAttribute('aria-busy', 'true');
                status.textContent = 'Guardando el orden…';
                try {
                    for (var step = 0; step < Math.abs(move.to - move.from); step++) {
                        var response = await requestJson('POST', handle.dataset.moveUrl, new URLSearchParams({ direction: move.to < move.from ? 'up' : 'down' }));
                        if (!response.ok || !response.data.ok) throw new Error('No se pudo guardar el orden. Recarga la página para comprobarlo antes de volver a intentarlo.');
                        // Refleja cada movimiento confirmado, incluso si el siguiente falla.
                        var item = handle.closest('[data-list-id]');
                        if (move.to < move.from) list.insertBefore(item, item.previousElementSibling);
                        else list.insertBefore(item.nextElementSibling, item);
                    }
                    status.textContent = 'Orden guardado.';
                } catch (error) {
                    status.classList.remove('sr-only');
                    status.classList.add('warn-text');
                    status.textContent = 'No se pudo guardar todo el orden. Recarga la página para comprobarlo antes de volver a intentarlo.';
                } finally {
                    busy = false;
                    list.removeAttribute('aria-busy');
                    var items = list.querySelectorAll('[data-list-id]');
                    items.forEach(function (item, index) {
                        item.querySelector('[value="up"]').form.querySelector('button').disabled = index === 0;
                        item.querySelector('[value="down"]').form.querySelector('button').disabled = index === items.length - 1;
                    });
                    handle.focus({ preventScroll: true });
                }
            });
        });
    }

    function isStandalone() {
        return navigator.standalone === true || ['standalone', 'fullscreen', 'minimal-ui'].some(function (mode) {
            return window.matchMedia('(display-mode: ' + mode + ')').matches;
        });
    }

    // «Modo TV»: se pide el token de este navegador en segundo plano y se guarda donde lo lee la
    // app de TV (mismo origen), y luego se abre /tv/. Antes era un POST que redirigía a /tv/: con
    // NokoPlay TV instalada, Android abría la app y la pestaña se quedaba en la URL del formulario,
    // que al volver a ella el navegador pedía con GET (405). Sin JavaScript sigue siendo un POST.
    function initTvLaunch() {
        var standalone = isStandalone();
        var touch = window.matchMedia('(pointer: coarse)').matches;
        document.querySelectorAll('form[data-tv-launch]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (e.defaultPrevented) return;
                e.preventDefault();
                // En el ordenador, en otra pestaña (abierta ya, para que no la bloquee el navegador);
                // en el móvil o en la app instalada, en la misma ventana: Atrás vuelve al panel.
                var win = form.target === '_blank' && !standalone && !touch ? window.open('', '_blank') : null;
                requestJson('POST', form.action).then(function (res) {
                    if (!res.ok || !res.data.token) throw new Error('sin token');
                    window.localStorage.setItem('nokoplay.token', res.data.token);
                    window.localStorage.removeItem('nokoplay.pendingPairing');
                    if (win) win.location.href = res.data.url;
                    else window.location.href = res.data.url;
                }).catch(function () {
                    if (win) win.close();
                    form.removeAttribute('target');
                    form.submit();
                });
            });
        });
    }

    // Aplicación instalable: service worker y avisos para instalarla (Inicio y Cuenta).
    function initPwa() {
        if ('serviceWorker' in navigator) {
            var register = function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); };
            if (document.readyState === 'complete') register();
            else window.addEventListener('load', register);
        }

        var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-pwa-install]'));
        if (!boxes.length || isStandalone()) return;

        var DISMISSED = 'nokoplay.installDismissed';
        var deferred = null;
        var installed = false;

        function dismissedRecently() {
            try { return Date.now() - Number(window.localStorage.getItem(DISMISSED) || 0) < 30 * 86400000; } catch (e) { return false; }
        }
        function dismiss() {
            try { window.localStorage.setItem(DISMISSED, String(Date.now())); } catch (e) { /* sin almacenamiento */ }
            boxes.forEach(function (box) { if (box.hasAttribute('data-pwa-dismissable')) box.hidden = true; });
        }
        /** Muestra cada aviso con la explicación que corresponde: botón, iPhone o menú del navegador. */
        function offer(hint) {
            if (installed) return;
            boxes.forEach(function (box) {
                if (box.hasAttribute('data-pwa-dismissable') && dismissedRecently()) return;
                box.querySelectorAll('[data-pwa-hint]').forEach(function (el) { el.hidden = el.getAttribute('data-pwa-hint') !== hint; });
                var button = box.querySelector('[data-pwa-install-button]');
                if (button) button.hidden = hint !== 'button';
                box.hidden = false;
            });
        }
        function hideAll() {
            boxes.forEach(function (box) { box.hidden = true; });
        }

        var ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (ios) offer('ios');

        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferred = e;
            offer('button');
        });

        // Navegadores sin instalación en un toque (Brave, Samsung Internet…): se explica el menú,
        // salvo que la app ya esté instalada (getInstalledRelatedApps, donde exista).
        if (!ios && window.matchMedia('(pointer: coarse)').matches) {
            setTimeout(function () {
                if (deferred || installed) return;
                var check = navigator.getInstalledRelatedApps ? navigator.getInstalledRelatedApps().catch(function () { return []; }) : Promise.resolve([]);
                check.then(function (apps) {
                    if (apps.length) installed = true;
                    else if (!deferred) offer('menu');
                });
            }, 3000);
        }

        boxes.forEach(function (box) {
            var button = box.querySelector('[data-pwa-install-button]');
            if (button) {
                button.addEventListener('click', function () {
                    if (!deferred) return;
                    var prompt = deferred;
                    deferred = null;
                    prompt.prompt();
                    // Si se rechaza, no se vuelve a ofrecer en el Inicio durante un tiempo.
                    prompt.userChoice.then(function (choice) {
                        if (choice.outcome === 'accepted') { installed = true; hideAll(); } else { dismiss(); hideAll(); }
                    });
                });
            }
            var close = box.querySelector('[data-pwa-dismiss]');
            if (close) close.addEventListener('click', dismiss);
        });

        window.addEventListener('appinstalled', function () { installed = true; hideAll(); });
    }

    onReady(function () {
        initPwa();
        initTvLaunch();
        initListSorting();
        initOnboardingCatalog();
        initTvPreview();
        initCodeAutosubmit();
        initConfirms();
        initMenus();
        initCheckTools();
        initYouTubeForm();
        initUploadForm();
        initYouTubeSearch();
        initDailyLimit();
        initWatching();
    });
})();
