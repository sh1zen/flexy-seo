(function ($, window) {
    'use strict';

    function locale(key, fallback) {
        var config = window.wpfsAdmin || {};
        var directMap = {
            wpfs_ajax_nonce: config.ajaxNonce,
            wpfs_autosave_saving: config.savingText,
            wpfs_autosave_saved: config.savedText,
            wpfs_autosave_error: config.errorText,
            wpfs_autosave_offline: config.offlineText,
            wpfs_autosave_pending: config.pendingText,
            wpfs_autosave_inactive: config.inactiveText
        };

        if (directMap[key]) {
            return directMap[key];
        }

        if (window.wps && window.wps.locale && typeof window.wps.locale.get === 'function') {
            return window.wps.locale.get(key, fallback);
        }

        return fallback;
    }

    function ensureToastHost() {
        var host = $('#wpfs-toast-host');

        if (!host.length) {
            host = $('<div/>', {
                id: 'wpfs-toast-host',
                'aria-live': 'polite',
                'aria-atomic': 'true'
            });
            $('body').append(host);
        }

        return host;
    }

    function showToast(status, text, timeout) {
        var host = ensureToastHost();
        var toast = host.find('.wpfs-toast').first();

        if (!toast.length) {
            toast = $('<div/>', {
                class: 'wpfs-toast',
                role: 'status'
            });
            host.append(toast);
        }

        toast
            .removeClass('is-saving is-success is-error is-warning')
            .addClass('is-' + status)
            .text(text);

        window.requestAnimationFrame(function () {
            toast.addClass('is-visible');
        });

        window.clearTimeout(toast.data('timer'));

        if (timeout !== 0) {
            toast.data('timer', window.setTimeout(function () {
                toast.removeClass('is-visible');
                window.setTimeout(function () {
                    toast.remove();
                }, 220);
            }, timeout || 1800));
        }
    }

    function setStatus(form, state, text) {
        var status = form.find('.wpfs-autosave-status');
        var icon = status.find('.dashicons');

        if (!status.length) {
            return;
        }

        status
            .removeClass('is-saving is-saved is-error is-pending')
            .addClass('is-' + state);

        icon
            .removeClass('dashicons-saved dashicons-update dashicons-warning dashicons-clock')
            .addClass(state === 'saving' ? 'dashicons-update' : state === 'error' ? 'dashicons-warning' : state === 'pending' ? 'dashicons-clock' : 'dashicons-saved');

        status.contents().filter(function () {
            return this.nodeType === 3;
        }).remove();

        status.append(document.createTextNode(' ' + text));
    }

    function tokenItems() {
        var configured = window.wpfsAdmin && $.isArray(window.wpfsAdmin.tokens) ? window.wpfsAdmin.tokens : [];

        if (configured.length) {
            return configured;
        }

        return [
            {token: 'title', label: 'title', description: 'Title'},
            {token: 'description', label: 'description', description: 'Description'},
            {token: 'sep', label: 'sep', description: 'Separator'},
            {token: 'sitename', label: 'sitename', description: 'Site name'},
            {token: 'excerpt', label: 'excerpt', description: 'Excerpt'}
        ];
    }

    function tokenSpan(token) {
        return $('<span/>', {
            class: 'wpfs-token-chip',
            contenteditable: 'false',
            'data-token': token,
            text: token
        })[0];
    }

    function renderTokenValue(editor, value) {
        var fragment = document.createDocumentFragment();
        var tokenRegex = /%%([^%]+)%%/g;
        var match;
        var offset = 0;

        while ((match = tokenRegex.exec(value)) !== null) {
            if (match.index > offset) {
                fragment.appendChild(document.createTextNode(value.slice(offset, match.index)));
            }

            fragment.appendChild(tokenSpan(match[1]));
            offset = match.index + match[0].length;
        }

        if (offset < value.length) {
            fragment.appendChild(document.createTextNode(value.slice(offset)));
        }

        editor.empty()[0].appendChild(fragment);
    }

    function serializeTokenEditor(editor) {
        function walk(node) {
            var output = '';

            if (node.nodeType === 3) {
                return node.nodeValue || '';
            }

            if (node.nodeType !== 1) {
                return '';
            }

            if ($(node).hasClass('wpfs-token-chip')) {
                return '%%' + ($(node).data('token') || node.textContent) + '%%';
            }

            if (node.nodeName === 'BR') {
                return '\n';
            }

            $(node).contents().each(function () {
                output += walk(this);
            });

            if ((node.nodeName === 'DIV' || node.nodeName === 'P') && output.slice(-1) !== '\n') {
                output += '\n';
            }

            return output;
        }

        return walk(editor[0]).replace(/\n$/, '');
    }

    function hasRawTokenText(editor) {
        var found = false;

        function walk(node) {
            if (found || $(node).hasClass('wpfs-token-chip')) {
                return;
            }

            if (node.nodeType === 3 && /%%([^%]+)%%/.test(node.nodeValue || '')) {
                found = true;
                return;
            }

            $(node).contents().each(function () {
                walk(this);
            });
        }

        walk(editor[0]);

        return found;
    }

    function placeCaretAfter(node) {
        var range = document.createRange();
        var selection = window.getSelection();

        range.setStartAfter(node);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function insertPlainText(text) {
        if (document.queryCommandSupported && document.queryCommandSupported('insertText')) {
            document.execCommand('insertText', false, text);
            return;
        }

        var selection = window.getSelection();
        var range;

        if (!selection.rangeCount) {
            return;
        }

        range = selection.getRangeAt(0);
        range.deleteContents();
        range.insertNode(document.createTextNode(text));
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function initTokenEditors() {
        var activeEditor = null;
        var activeTextarea = null;
        var savedRange = null;
        var dropdown = null;

        function sync(textarea, editor) {
            textarea.val(serializeTokenEditor(editor));
            textarea.trigger('input').trigger('change');
        }

        function closeDropdown() {
            if (dropdown) {
                dropdown.remove();
                dropdown = null;
            }
        }

        function insertToken(token) {
            var selection = window.getSelection();
            var range = savedRange;
            var chip = tokenSpan(token);
            var spacer = document.createTextNode(' ');
            var fragment = document.createDocumentFragment();

            closeDropdown();

            if (!activeEditor || !activeTextarea) {
                return;
            }

            activeEditor.focus();

            if (range) {
                selection.removeAllRanges();
                selection.addRange(range);
            }
            else {
                range = document.createRange();
                range.selectNodeContents(activeEditor[0]);
                range.collapse(false);
            }

            if (range.startContainer.nodeType === 3) {
                var text = range.startContainer.nodeValue || '';
                var start = range.startOffset;
                var trigger = text.slice(0, start).match(/%{1,2}[\w{}.-]*$/);

                if (trigger) {
                    range.setStart(range.startContainer, start - trigger[0].length);
                }
            }

            range.deleteContents();
            fragment.appendChild(chip);
            fragment.appendChild(spacer);
            range.insertNode(fragment);
            placeCaretAfter(spacer);
            sync(activeTextarea, activeEditor);
        }

        function filterDropdown(query) {
            var list = dropdown.find('.wpfs-token-menu-list');
            var items = tokenItems();
            var needle = query.toLowerCase();
            var found = 0;

            list.empty();

            items.forEach(function (item) {
                var haystack = (item.token + ' ' + item.label + ' ' + item.description).toLowerCase();

                if (needle && haystack.indexOf(needle) === -1) {
                    return;
                }

                found++;
                $('<button/>', {
                    type: 'button',
                    class: 'wpfs-token-menu-item',
                    'data-token': item.token
                }).append(
                    $('<strong/>', {text: item.label || item.token}),
                    $('<span/>', {text: item.description || ''})
                ).appendTo(list);
            });

            if (!found) {
                $('<div/>', {
                    class: 'wpfs-token-menu-empty',
                    text: window.wpfsAdmin && window.wpfsAdmin.tokenEmptyText ? window.wpfsAdmin.tokenEmptyText : 'No replacements found'
                }).appendTo(list);
            }
        }

        function openDropdown(editor, textarea) {
            var selection = window.getSelection();
            var rect = editor[0].getBoundingClientRect();

            if (selection.rangeCount) {
                savedRange = selection.getRangeAt(0).cloneRange();
            }

            activeEditor = editor;
            activeTextarea = textarea;
            closeDropdown();

            dropdown = $('<div/>', {class: 'wpfs-token-menu'}).append(
                $('<input/>', {
                    type: 'search',
                    class: 'wpfs-token-menu-search',
                    placeholder: window.wpfsAdmin && window.wpfsAdmin.tokenSearchText ? window.wpfsAdmin.tokenSearchText : 'Search replacements...'
                }),
                $('<div/>', {class: 'wpfs-token-menu-list'})
            );

            $('body').append(dropdown);
            dropdown.css({
                left: Math.max(16, rect.left + window.scrollX) + 'px',
                top: rect.bottom + window.scrollY + 6 + 'px',
                width: Math.min(420, rect.width) + 'px'
            });

            filterDropdown('');
        }

        function queryFromCaret(editor) {
            var selection = window.getSelection();
            var text;
            var match;

            if (!selection.rangeCount || !editor[0].contains(selection.anchorNode) || selection.anchorNode.nodeType !== 3) {
                return '';
            }

            text = (selection.anchorNode.nodeValue || '').slice(0, selection.anchorOffset);
            match = text.match(/%{1,2}([\w{}.-]*)$/);

            return match ? match[1] : '';
        }

        function previousTokenFromCaret(editor) {
            var selection = window.getSelection();
            var range;
            var node;

            if (!selection.rangeCount || !selection.isCollapsed || !editor[0].contains(selection.anchorNode)) {
                return null;
            }

            range = selection.getRangeAt(0);
            node = range.startContainer;

            if (node.nodeType === 3 && range.startOffset > 0) {
                return null;
            }

            if (node.nodeType === 3) {
                node = node.previousSibling || node.parentNode.previousSibling;
            }
            else {
                node = node.childNodes[range.startOffset - 1] || node.previousSibling;
            }

            return node && $(node).hasClass('wpfs-token-chip') ? node : null;
        }

        function nextTokenFromCaret(editor) {
            var selection = window.getSelection();
            var range;
            var node;

            if (!selection.rangeCount || !selection.isCollapsed || !editor[0].contains(selection.anchorNode)) {
                return null;
            }

            range = selection.getRangeAt(0);
            node = range.startContainer;

            if (node.nodeType === 3 && range.startOffset < (node.nodeValue || '').length) {
                return null;
            }

            if (node.nodeType === 3) {
                node = node.nextSibling || node.parentNode.nextSibling;
            }
            else {
                node = node.childNodes[range.startOffset] || node.nextSibling;
            }

            return node && $(node).hasClass('wpfs-token-chip') ? node : null;
        }

        $('textarea.wps, input[type="text"].wps:not(.wps-input-upload)').each(function () {
            var textarea = $(this);
            var editor;
            var isInput = textarea.is('input');

            if (textarea.data('wpfs-token-editor')) {
                return;
            }

            textarea.data('wpfs-token-editor', true);
            editor = $('<div/>', {
                class: 'wpfs-token-editor' + (isInput ? ' is-single-line' : ''),
                contenteditable: 'true',
                role: 'textbox',
                'aria-multiline': isInput ? 'false' : 'true',
                'aria-label': textarea.closest('row').find('.wps-option strong').first().text() || textarea.attr('name')
            });

            renderTokenValue(editor, textarea.val());
            textarea
                .addClass('wpfs-token-source')
                .attr('aria-hidden', 'true')
                .css({
                    display: 'none',
                    position: 'absolute',
                    width: 0,
                    height: 0,
                    opacity: 0,
                    pointerEvents: 'none'
                })
                .after(editor);

            editor.on('keydown', function (event) {
                var token;

                if (event.key === '%' || event.key === 'Dead') {
                    window.setTimeout(function () {
                        openDropdown(editor, textarea);
                    }, 0);
                    return;
                }

                if (event.key === 'Backspace') {
                    token = previousTokenFromCaret(editor);

                    if (token) {
                        event.preventDefault();
                        $(token).remove();
                        sync(textarea, editor);
                    }
                }

                if (event.key === 'Delete') {
                    token = nextTokenFromCaret(editor);

                    if (token) {
                        event.preventDefault();
                        $(token).remove();
                        sync(textarea, editor);
                    }
                }

                if (event.key === 'Enter' && dropdown) {
                    event.preventDefault();
                    dropdown.find('.wpfs-token-menu-item:visible').first().trigger('click');
                }

                if (event.key === 'Enter' && isInput) {
                    event.preventDefault();
                    closeDropdown();
                    sync(textarea, editor);
                }
            });

            editor.on('input blur', function () {
                var value = serializeTokenEditor(editor);

                if (hasRawTokenText(editor)) {
                    renderTokenValue(editor, value);
                    closeDropdown();
                }
                else if (dropdown && activeEditor && activeEditor[0] === editor[0]) {
                    filterDropdown(queryFromCaret(editor));
                }

                sync(textarea, editor);
            });

            editor.on('focus', function () {
                activeEditor = editor;
                activeTextarea = textarea;
            });

            editor.on('paste', function (event) {
                var clipboard = event.originalEvent.clipboardData || window.clipboardData;

                if (!clipboard) {
                    return;
                }

                event.preventDefault();
                insertPlainText(clipboard.getData('text/plain'));
            });
        });

        $(document)
            .on('input', '.wpfs-token-menu-search', function () {
                filterDropdown($(this).val());
            })
            .on('keydown', '.wpfs-token-menu-search', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    dropdown.find('.wpfs-token-menu-item:visible').first().trigger('click');
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeDropdown();
                    if (activeEditor) {
                        activeEditor.trigger('focus');
                    }
                }
            })
            .on('click', '.wpfs-token-menu-item', function () {
                insertToken($(this).data('token'));
            })
            .on('mousedown', '.wpfs-token-menu', function (event) {
                if (!$(event.target).is('.wpfs-token-menu-search')) {
                    event.preventDefault();
                }
            })
            .on('mousedown', function (event) {
                if (dropdown && !$(event.target).closest('.wpfs-token-menu, .wpfs-token-editor').length) {
                    closeDropdown();
                }
            });
    }

    function initAutosave() {
        var forms = $('#wps-options, .wpfs-breadcrumbs-page form[action="options.php"], .wpfs-core-settings-page form[action="options.php"]');

        if (!forms.length) {
            return;
        }

        var nonce = locale('wpfs_ajax_nonce', '');
        var ajaxUrl = (window.wpfsAdmin && window.wpfsAdmin.ajaxUrl) || window.ajaxurl;

        if (!nonce || !ajaxUrl) {
            showToast('error', locale('wpfs_autosave_inactive', 'Autosave unavailable. Reload the page and try again.'), 3200);
            return;
        }

        function autosaveModule(form) {
            var change = form.find(':input[name$="[change]"]').val();

            if (change) {
                return change;
            }

            var optionPanel = form.find(':input[name="option_panel"]').val();

            if (optionPanel) {
                return String(optionPanel).replace(/^settings-/, '');
            }

            return '';
        }

        function autosaveAction(form) {
            var change = autosaveModule(form);

            if (change === 'breadcrumbs') {
                return 'wpfs_autosave_breadcrumbs_settings';
            }

            if (form.closest('.wpfs-core-settings-page').length) {
                return 'wpfs_autosave_core_settings';
            }

            return 'wpfs_autosave_settings';
        }

        forms.each(function () {
            var form = $(this);

            if (form.data('wpfs-autosave-init')) {
                return;
            }

            form.data('wpfs-autosave-init', true);
            form.data('wpopt-autosave-init', true);

            var submit = form.find('.wps-submit');
            var status = form.find('.wpfs-autosave-status');

            if (!status.length) {
                status = $('<span/>', {
                    class: 'wpfs-autosave-status is-saved',
                    role: 'status',
                    'aria-live': 'polite'
                }).append(
                    $('<span/>', {
                        class: 'dashicons dashicons-saved',
                        'aria-hidden': 'true'
                    }),
                    document.createTextNode(' ' + locale('wpfs_autosave_saved', 'Changes saved'))
                );

                if (submit.length) {
                    submit.append(status);
                }
                else {
                    form.prepend(status);
                }
            }

            submit.find('input[type="submit"], button[type="submit"], .button-primary').remove();
            if (!$.trim(submit.text()) && !submit.children().length) {
                submit.hide();
            }

            var action = autosaveAction(form);
            var lastSerialized = form.serialize();
            var timer = null;
            var saving = false;
            var queued = false;
            var dirty = false;
            var pollTimer = null;

            function saveNow() {
                var payload = form.serialize();

                if (payload === lastSerialized) {
                    dirty = false;
                    setStatus(form, 'saved', locale('wpfs_autosave_saved', 'Changes saved'));
                    return;
                }

                if (saving) {
                    queued = true;
                    return;
                }

                saving = true;
                queued = false;
                dirty = true;

                setStatus(form, 'saving', locale('wpfs_autosave_saving', 'Saving changes...'));
                showToast('saving', locale('wpfs_autosave_saving', 'Saving changes...'), 0);

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: action,
                        nonce: nonce,
                        module: autosaveModule(form),
                        form: payload
                    }
                }).done(function (response) {
                    if (response && response.status === 'success') {
                        lastSerialized = payload;
                        dirty = false;
                        setStatus(form, 'saved', locale('wpfs_autosave_saved', 'Changes saved'));
                        showToast('success', response.data && response.data.text ? response.data.text : locale('wpfs_autosave_saved', 'Changes saved'));
                    }
                    else {
                        setStatus(form, 'error', locale('wpfs_autosave_error', 'Autosave failed'));
                        showToast('error', response && response.data && response.data.text ? response.data.text : locale('wpfs_autosave_error', 'Autosave failed'), 2600);
                    }
                }).fail(function () {
                    setStatus(form, 'error', locale('wpfs_autosave_offline', 'Connection error. Changes are not saved yet.'));
                    showToast('error', locale('wpfs_autosave_offline', 'Connection error. Changes are not saved yet.'), 3200);
                }).always(function () {
                    saving = false;

                    if (queued || form.serialize() !== lastSerialized) {
                        queued = false;
                        window.setTimeout(saveNow, 250);
                    }
                });
            }

            function queueSave() {
                dirty = true;
                setStatus(form, 'pending', locale('wpfs_autosave_pending', 'Unsaved changes'));
                window.clearTimeout(timer);
                timer = window.setTimeout(saveNow, 750);
            }

            form.on('submit', function (event) {
                event.preventDefault();
                window.clearTimeout(timer);
                saveNow();
            });

            form.on('input change', ':input:not([type="submit"]):not([type="button"]):not([type="hidden"])', queueSave);

            form.on('click', '.wps-dropdown li, .wps-uploader__init', function () {
                window.setTimeout(function () {
                    if (form.serialize() !== lastSerialized) {
                        queueSave();
                    }
                }, 250);
            });

            pollTimer = window.setInterval(function () {
                if (form.serialize() !== lastSerialized && !saving) {
                    queueSave();
                }
            }, 1800);

            $(window).on('beforeunload.wpfsAutosave', function (event) {
                if (dirty || saving) {
                    var message = locale('wpfs_autosave_pending', 'Unsaved changes');
                    event.returnValue = message;
                    return message;
                }

                window.clearInterval(pollTimer);
            });
        });
    }

    function initSnippetPreview() {
        var preview = $('[data-wpfs-snippet-preview]');
        var form = $('#wps-options');
        var config = window.wpfsAdmin && window.wpfsAdmin.preview ? window.wpfsAdmin.preview : {};
        var labels = config.labels || {};
        var tokenDefaults = config.tokens || {};

        if (!preview.length || !form.length || preview.data('wpfs-preview-init')) {
            return;
        }

        preview.data('wpfs-preview-init', true);

        function activePanel() {
            var selected = $('.wps-ar-tab[aria-selected="true"]').first();
            var panel;

            if (selected.length && selected.attr('aria-controls')) {
                panel = $('panel').filter(function () {
                    return this.id === selected.attr('aria-controls');
                }).first();

                if (panel.length) {
                    return panel;
                }
            }

            panel = $('panel.wps-ar-tabcontent[aria-hidden="false"]').first();

            if (panel.length) {
                return panel;
            }

            return $('panel.wps-ar-tabcontent').not('#seo-vars').first();
        }

        function cleanText(value) {
            value = $('<textarea/>').html(value || '').text();
            value = value.replace(/<[^>]*>/g, ' ');
            value = value.replace(/%%[^%]+%%/g, ' ');
            value = value.replace(/\s+/g, ' ');
            value = value.replace(/\s+([,.;:!?])/g, '$1');

            return $.trim(value);
        }

        function resolveTokens(value) {
            var tokens = $.extend({}, tokenDefaults, {
                sitename: config.siteName || '',
                sitedesc: config.siteDescription || '',
                language: config.language || '',
                date: config.date || '',
                time: config.time || ''
            });

            return cleanText((value || '').replace(/%%([^%]+)%%/g, function (match, token) {
                if (Object.prototype.hasOwnProperty.call(tokens, token)) {
                    return tokens[token];
                }

                if (token.indexOf('meta_') === 0) {
                    return '';
                }

                if (token.charAt(0) === '{' && token.charAt(token.length - 1) === '}') {
                    return '';
                }

                return '';
            }));
        }

        function fieldValue(panel, matcher) {
            var field = panel.find('textarea, input[type="text"]').filter(function () {
                var id = this.id || '';

                if ($(this).hasClass('wps-input-upload')) {
                    return false;
                }

                return matcher(id, this);
            }).first();

            return field.length ? field.val() : '';
        }

        function titleTemplate(panel) {
            return fieldValue(panel, function (id) {
                return /(^|\.)title$/.test(id) && id !== 'title.separator';
            });
        }

        function descriptionTemplate(panel) {
            return fieldValue(panel, function (id) {
                return /(^|\.)meta_desc$/.test(id);
            });
        }

        function imageUrl(panel) {
            var local = panel.find('.wps-input-upload input[type="text"]').filter(function () {
                return $(this).val();
            }).first();
            var global = $('[id="social.facebook.logo_url"], [id="org.logo_url.wide"], [id="org.logo_url.small"]').filter(function () {
                return $(this).val();
            }).first();

            return (local.length ? local.val() : (global.length ? global.val() : config.defaultImage || ''));
        }

        function previewUrl() {
            if (config.sampleUrl) {
                return config.sampleUrl;
            }

            var base = config.homeUrl || '';
            var slug = labels.exampleUrl || 'example-page';

            if (!base) {
                return slug;
            }

            return base.replace(/\/+$/, '') + '/' + slug.replace(/^\/+/, '');
        }

        function previewDomain(url) {
            var anchor = document.createElement('a');

            anchor.href = url;

            return anchor.hostname || url;
        }

        function setCounter(target, label, value, limit) {
            var count = value.length;

            target
                .toggleClass('is-warning', limit > 0 && count > limit)
                .text(label + ': ' + count + '/' + limit);
        }

        function update() {
            var panel = activePanel();
            var title = resolveTokens(titleTemplate(panel)) || config.defaultTitle || tokenDefaults.title || '';
            var description = resolveTokens(descriptionTemplate(panel)) || config.defaultDescription || tokenDefaults.description || '';
            var url = previewUrl();
            var image = imageUrl(panel);
            var imageHost = preview.find('[data-wpfs-preview-image]');

            preview.find('[data-wpfs-preview-title]').text(title);
            preview.find('[data-wpfs-preview-description]').text(description);
            preview.find('[data-wpfs-preview-social-title]').text(title);
            preview.find('[data-wpfs-preview-social-description]').text(description);
            preview.find('[data-wpfs-preview-url]').text(url);
            preview.find('[data-wpfs-preview-domain]').text(previewDomain(url));

            if (image) {
                imageHost
                    .addClass('has-image')
                    .empty()
                    .append($('<img/>', {
                        src: image,
                        alt: labels.imageAlt || 'Social preview image'
                    }));
            }
            else {
                imageHost
                    .removeClass('has-image')
                    .empty()
                    .append($('<span/>', {text: labels.noImage || 'No image selected'}));
            }

            setCounter(preview.find('[data-wpfs-title-count]'), labels.titleCounter || 'Title length', title, config.titleLimit || 60);
            setCounter(preview.find('[data-wpfs-description-count]'), labels.descriptionCounter || 'Description length', description, config.descriptionLimit || 160);
        }

        form.on('input change', 'textarea, input[type="text"], input[type="hidden"]', update);
        $(document).on('input blur', '.wpfs-token-editor', update);
        $(document).on('click', '.wps-ar-tab, .wps-dropdown li, .wps-uploader__init', function () {
            window.setTimeout(update, 300);
        });

        update();
    }

    function initRedirectUploader() {
        $('[data-wpfs-file-upload]').each(function () {
            var upload = $(this);
            var input = upload.find('[data-wpfs-file-input]');
            var fileName = upload.find('[data-wpfs-file-name]');
            var emptyText = fileName.text();

            if (upload.data('wpfs-file-upload-init')) {
                return;
            }

            upload.data('wpfs-file-upload-init', true);

            input.on('change', function () {
                var file = this.files && this.files.length ? this.files[0] : null;

                fileName
                    .toggleClass('has-file', !!file)
                    .text(file ? file.name : emptyText);
            });
        });
    }

    $(function () {
        initTokenEditors();
        initAutosave();
        initSnippetPreview();
        initRedirectUploader();
    });
})(jQuery, window);
