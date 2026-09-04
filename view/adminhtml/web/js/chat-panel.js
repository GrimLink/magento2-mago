(function() {
    var config = window.MAGGY_CONFIG;
    var formKey = config.formKey;
    var skills = config.skills;
    var commands = config.commands || [];
    // Widget and skill-card builders (js/mago-ui.js); loaded before this file by panel.phtml.
    var UI = window.MagoUI;
    var conversationId = null;
    var busy = false;
    var showingHistory = false;
    var slashActive = false;
    var slashIndex = 0;
    var filteredItems = [];
    var SS_KEY_OPEN = 'maggy_open';
    var SS_KEY_CONV = 'maggy_conv';
    var SS_KEY_FULL = 'maggy_fullsize';

    function saveState() {
        try {
            sessionStorage.setItem(SS_KEY_OPEN, chat.classList.contains('is-open') ? '1' : '0');
            sessionStorage.setItem(SS_KEY_CONV, conversationId ? String(conversationId) : '');
            sessionStorage.setItem(SS_KEY_FULL, chat.classList.contains('is-fullsize') ? '1' : '0');
        } catch(e) {}
    }

    function qs(s) { return document.querySelector(s); }
    var toggle = qs('#maggy-toggle');
    var chat = qs('#maggy-chat');
    var msgs = qs('#maggy-messages');
    var input = qs('#maggy-input');
    var loading = qs('#maggy-loading');
    var sendBtn = qs('#maggy-send');
    var histList = qs('#maggy-history-list');
    var inputArea = qs('#maggy-input-area');
    var slashMenu = qs('#maggy-slash-menu');

    // A theme without the header container renders the panel without its
    // toggle, so bail out before anything binds to a missing element.
    if (!toggle || !chat) {
        return;
    }

    function clearMsgs() {
        var nodes = msgs.querySelectorAll('.maggy-message, .maggy-date-sep');
        for (var i = 0; i < nodes.length; i++) nodes[i].remove();
        loading.style.display = 'none';
    }

    // The header subtitle names the conversation being viewed; empty on a fresh chat.
    function setSubtitle(text) {
        var el = qs('#maggy-subtitle');
        if (el) el.textContent = text || '';
    }

    // Busy drives the "Working" pill in the header and the send button state.
    function setBusy(state) {
        busy = state;
        chat.classList.toggle('is-busy', state);
        loading.style.display = state ? '' : 'none';
        sendBtn.disabled = state;
    }

    // The header icon is the only way in or out of the panel, so its tooltip,
    // aria state and active style all have to follow whatever it will do next.
    function syncToggleLabel() {
        var open = chat.classList.contains('is-open');
        var label = open ? toggle.getAttribute('data-label-close') : toggle.getAttribute('data-label-open');
        if (label) {
            toggle.title = label;
        }
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.classList.toggle('is-active', open);
    }

    function openPanel() {
        chat.classList.add('is-open');
        document.body.classList.add('maggy-active');
        syncToggleLabel();
        if (msgs.children.length <= 1 && !conversationId) {
            showGreeting();
        }
        saveState();
        input.focus();
    }

    function closePanel() {
        chat.classList.remove('is-open', 'is-fullsize');
        document.body.classList.remove('maggy-active', 'maggy-fullsize');
        var expandBtn = qs('#maggy-expand');
        expandBtn.title = 'Full size';
        expandBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
        syncToggleLabel();
        if (showingHistory) hideHistory();
        hideSlashMenu();
        saveState();
    }

    toggle.onclick = function() {
        if (chat.classList.contains('is-open')) {
            closePanel();
        } else {
            openPanel();
        }
    };
    qs('#maggy-close').onclick = closePanel;
    qs('#maggy-expand').onclick = function() {
        var isFullsize = chat.classList.toggle('is-fullsize');
        document.body.classList.toggle('maggy-fullsize', isFullsize);
        var expandBtn = qs('#maggy-expand');
        if (isFullsize) {
            expandBtn.title = 'Side panel';
            expandBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
        } else {
            expandBtn.title = 'Full size';
            expandBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
        }
        saveState();
    };
    qs('#maggy-new').onclick = function() {
        if (showingHistory) hideHistory();
        clearMsgs();
        conversationId = null;
        showGreeting();
        saveState();
    };
    qs('#maggy-history').onclick = function() {
        if (showingHistory) {
            hideHistory();
        } else {
            loadHistory();
        }
    };

    // Slash menu: two kinds of entries. Commands ("/cache flush") run directly against
    // Magento and are sent as typed; skills expand to a prompt for the assistant.
    var slashItems = [];
    commands.forEach(function(c) {
        c.subcommands.forEach(function(sub) {
            slashItems.push({
                type: 'command',
                command: c.name,
                full: c.name + ' ' + sub.name,
                label: '/' + c.name + ' ' + sub.name + (sub.args ? ' ' + sub.args : ''),
                description: sub.description,
                readOnly: sub.readOnly
            });
        });
    });
    skills.forEach(function(s) {
        slashItems.push({
            type: 'skill',
            full: s.name,
            label: '/' + s.name,
            description: s.description,
            readOnly: s.readOnly,
            skill: s
        });
    });

    function matchesSlashItem(item, q) {
        if (!q) return true;
        if (item.type === 'command') {
            // Once arguments follow the command the entry drops out, so Enter sends instead of completing.
            return item.full.indexOf(q) !== -1;
        }
        return item.full.indexOf(q) !== -1 || item.description.toLowerCase().indexOf(q) !== -1;
    }

    function showSlashMenu(filter) {
        var q = (filter || '').toLowerCase();
        filteredItems = slashItems.filter(function(item) { return matchesSlashItem(item, q); });
        if (!filteredItems.length) {
            hideSlashMenu();
            return;
        }
        slashIndex = 0;
        slashActive = true;
        slashMenu.innerHTML = '';
        // S14 skill menu: one row per entry with its risk colour. Commands and skills
        // get their own menu block when both kinds survive the filter, so the group
        // headings the command list needs stay visible.
        var hasBoth = filteredItems.some(function(i) { return i.type === 'command'; })
            && filteredItems.some(function(i) { return i.type === 'skill'; });
        var groups = hasBoth
            ? [
                {title: 'Commands', items: filteredItems.filter(function(i) { return i.type === 'command'; })},
                {title: 'Skills', items: filteredItems.filter(function(i) { return i.type === 'skill'; })}
            ]
            : [{title: null, items: filteredItems}];
        groups.forEach(function(group) {
            slashMenu.appendChild(UI.skillMenu({
                itemClass: 'maggy-slash-item',
                title: group.title,
                skills: group.items.map(function(item) {
                    return {
                        name: item.full,
                        title: item.label,
                        description: item.description,
                        risk: item.readOnly ? 'read' : 'write'
                    };
                }),
                onSelect: function(entry, i) { selectSlashItem(group.items[i]); }
            }));
        });
        slashMenu.classList.add('is-visible');
    }

    // "cms_data" reads as "Cms data" in a card title; the mono badge keeps the real name.
    function skillTitle(toolName) {
        var t = String(toolName || '').replace(/_/g, ' ');
        return t.charAt(0).toUpperCase() + t.slice(1);
    }

    function hideSlashMenu() {
        slashActive = false;
        slashMenu.classList.remove('is-visible');
        slashMenu.innerHTML = '';
    }

    function selectSlashItem(item) {
        input.value = item.type === 'command'
            ? '/' + item.full + ' '
            : 'Use the ' + item.skill.name + ' skill to ';
        autoGrow();
        hideSlashMenu();
        input.focus();
    }

    // The input already holds a complete command ("/cache flush") or a bare command name
    // ("/cache", which the backend answers with its usage), so Enter should send, not complete.
    function isTypedCommand(item) {
        if (!item || item.type !== 'command') return false;
        var typed = input.value.trim().toLowerCase();
        return typed === '/' + item.full || typed === '/' + item.command;
    }

    function updateSlashHighlight() {
        var items = slashMenu.querySelectorAll('.maggy-slash-item');
        items.forEach(function(el, i) {
            el.classList.toggle('is-active', i === slashIndex);
        });
        if (items[slashIndex]) items[slashIndex].scrollIntoView({block:'nearest'});
    }

    function autoGrow() {
        var MAX = 240;
        // border-box: scrollHeight excludes the border, so add it back or the
        // field ends up 2px short and shows a scrollbar on a single line.
        var border = input.offsetHeight - input.clientHeight;
        input.style.height = 'auto';
        var full = input.scrollHeight + border;
        input.style.height = Math.min(full, MAX) + 'px';
        input.style.overflowY = full > MAX ? 'auto' : 'hidden';
    }

    input.addEventListener('input', function() {
        autoGrow();
        var v = input.value;
        sendBtn.classList.toggle('is-idle', !v.trim());
        if (v.charAt(0) === '/') {
            var filter = v.substring(1);
            showSlashMenu(filter);
        } else {
            hideSlashMenu();
        }
    });

    input.onkeydown = function(e) {
        if (slashActive) {
            if (e.keyCode === 38) { // up
                e.preventDefault();
                slashIndex = Math.max(0, slashIndex - 1);
                updateSlashHighlight();
                return;
            }
            if (e.keyCode === 40) { // down
                e.preventDefault();
                slashIndex = Math.min(filteredItems.length - 1, slashIndex + 1);
                updateSlashHighlight();
                return;
            }
            if (e.keyCode === 13 && isTypedCommand(filteredItems[slashIndex])) { // enter on a complete command runs it
                e.preventDefault();
                hideSlashMenu();
                send();
                return;
            }
            if (e.keyCode === 13 || e.keyCode === 9) { // enter or tab
                e.preventDefault();
                if (filteredItems[slashIndex]) selectSlashItem(filteredItems[slashIndex]);
                return;
            }
            if (e.keyCode === 27) { // escape
                e.preventDefault();
                hideSlashMenu();
                return;
            }
        }
        if (e.keyCode === 13 && !e.shiftKey) { e.preventDefault(); send(); }
    };
    sendBtn.onclick = send;

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T') + 'Z');
        var now = new Date();
        var diff = now - d;
        if (diff < 86400000) {
            return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        }
        if (diff < 604800000) {
            var days = Math.floor(diff / 86400000);
            return days + 'd ago';
        }
        return d.toLocaleDateString([], {month:'short', day:'numeric'});
    }

    function loadHistory() {
        showingHistory = true;
        msgs.style.display = 'none';
        loading.style.display = 'none';
        inputArea.style.display = 'none';
        histList.style.display = '';
        histList.innerHTML = '<div style="padding:16px;color:#999;font-size:13px;">Loading...</div>';

        fetch(config.historyUrl, {
            headers: {'X-Requested-With':'XMLHttpRequest'},
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            histList.innerHTML = '';
            if (!data.conversations || !data.conversations.length) {
                histList.innerHTML = '<div style="padding:16px;color:#999;font-size:13px;">No previous chats</div>';
                return;
            }
            data.conversations.forEach(function(conv) {
                var item = document.createElement('div');
                item.className = 'maggy-history-item';
                item.innerHTML = '<span class="maggy-history-item-title">' + esc(conv.title || 'Untitled') + '</span>'
                    + '<span class="maggy-history-item-date">' + formatDate(conv.updated_at) + '</span>'
                    + '<button type="button" class="maggy-history-item-delete" title="Delete">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
                    + '</button>';

                item.querySelector('.maggy-history-item-title').onclick = function() {
                    loadConversation(conv.entity_id, conv.title);
                };
                item.querySelector('.maggy-history-item-delete').onclick = function(e) {
                    e.stopPropagation();
                    deleteConversation(conv.entity_id, item);
                };
                histList.appendChild(item);
            });
        })
        .catch(function() {
            histList.innerHTML = '<div style="padding:16px;color:#e22626;font-size:13px;">Failed to load history</div>';
        });
    }

    function hideHistory() {
        showingHistory = false;
        histList.style.display = 'none';
        msgs.style.display = '';
        inputArea.style.display = '';
    }

    function loadConversation(id, title) {
        hideHistory();
        clearMsgs();
        lastDateLabel = '';
        conversationId = id;
        chat.classList.remove('is-empty');
        setSubtitle(title || '');
        loading.style.display = '';

        var fd = new FormData();
        fd.append('form_key', formKey);
        fd.append('conversation_id', id);

        fetch(config.loadUrl, {
            method: 'POST',
            headers: {'X-Requested-With':'XMLHttpRequest'},
            body: fd,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            loading.style.display = 'none';
            var loaded = data.messages || [];
            if (!loaded.length) {
                showGreeting();
                return;
            }
            loaded.forEach(function(m) {
                if (m.role === 'user' || m.role === 'assistant') {
                    addDateSep(m.created_at);
                    addMsg(m.role, renderMd(m.content || ''), m.created_at);
                }
            });
            saveState();
        })
        .catch(function() {
            loading.style.display = 'none';
            conversationId = null;
            showGreeting();
            saveState();
        });
    }

    function deleteConversation(id, el) {
        var fd = new FormData();
        fd.append('form_key', formKey);
        fd.append('conversation_id', id);

        fetch(config.deleteUrl, {
            method: 'POST',
            headers: {'X-Requested-With':'XMLHttpRequest'},
            body: fd,
            credentials: 'same-origin'
        }).then(function(r) { return r.json(); }).then(function() {
            el.remove();
            if (conversationId === id) {
                conversationId = null;
                clearMsgs();
            }
        });
    }

    // Configure marked.js once if available
    if (window.marked) {
        var markedRenderer = new marked.Renderer();
        markedRenderer.link = function(href, title, text) {
            if (typeof href === 'object' && href !== null) { text = href.text; title = href.title; href = href.href; }
            var isAdmin = href && (href.indexOf('/admin') !== -1 || href.charAt(0) === '/');
            var target = isAdmin ? '_self' : '_blank';
            var titleAttr = title ? ' title="' + title + '"' : '';
            return '<a href="' + href + '" target="' + target + '" rel="noopener"' + titleAttr + '>' + text + '</a>';
        };
        markedRenderer.table = function(token) {
            // Render using the default logic but wrap in a scrollable div
            var html = marked.Renderer.prototype.table.call(this, token);
            return '<div class="maggy-table-wrap">' + html + '</div>';
        };
        // A ```mago fenced block holds a widget spec ({"type": "stat", ...} or a
        // list of them) and renders as the matching widget. While the block is
        // still streaming in, the JSON is incomplete and a skeleton holds its place.
        markedRenderer.code = function(token) {
            var lang = typeof token === 'object' ? token.lang : arguments[1];
            if (lang === 'mago' && UI) {
                var code = typeof token === 'object' ? token.text : token;
                return UI.renderJson(code) || UI.skeleton().outerHTML;
            }
            return marked.Renderer.prototype.code.apply(this, arguments);
        };
        marked.use({ renderer: markedRenderer, gfm: true, breaks: true });
    }

    function renderMd(t) {
        if (!t) return '';
        // Use marked.js if available (loaded from CDN)
        if (window.marked) {
            return marked.parse(t);
        }
        // Fallback: simple regex-based renderer
        var h = esc(t);
        h = h.replace(/```(\w*)\n([\s\S]*?)```/g, function(m,l,c){ return '<pre><code>'+c.trim()+'</code></pre>'; });
        h = h.replace(/`([^`]+)`/g, '<code>$1</code>');
        h = h.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        h = h.replace(/\*(.+?)\*/g, '<em>$1</em>');
        h = h.replace(/\[([^\]]+)\]\(((?:https?:\/\/[^ )]+|\/[^ )]+))\)/g, function(m, text, url) {
            url = url.replace(/[\n\r]+/g, '');
            var isAdmin = url.indexOf('/admin') !== -1 || url.charAt(0) === '/';
            var target = isAdmin ? '_self' : '_blank';
            return '<a href="' + url + '" target="' + target + '" rel="noopener">' + text + '</a>';
        });
        h = h.replace(/(https?:\/\/[^ <\n]+)/g, function(m, url, offset) {
            var before = h.substring(Math.max(0, offset - 6), offset);
            if (before.indexOf('href=') !== -1 || before.indexOf('">') !== -1) return m;
            var isAdmin = url.indexOf('/admin') !== -1;
            var target = isAdmin ? '_self' : '_blank';
            return '<a href="' + url + '" target="' + target + '" rel="noopener">' + url + '</a>';
        });
        h = h.replace(/\n\n/g, '</p><p>');
        h = h.replace(/\n/g, '<br>');
        return '<p>' + h + '</p>';
    }

    var lastDateLabel = '';

    function addDateSep(dateStr) {
        if (!dateStr) return;
        var d = new Date(dateStr.replace(' ', 'T') + 'Z');
        var label = d.toLocaleDateString([], {weekday:'short', month:'short', day:'numeric'});
        var today = new Date();
        if (d.toDateString() === today.toDateString()) label = 'Today';
        var yest = new Date(today); yest.setDate(yest.getDate()-1);
        if (d.toDateString() === yest.toDateString()) label = 'Yesterday';
        if (label !== lastDateLabel) {
            lastDateLabel = label;
            var sep = document.createElement('div');
            sep.className = 'maggy-date-sep';
            sep.innerHTML = '<span>' + esc(label) + '</span>';
            msgs.insertBefore(sep, loading);
        }
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T') + 'Z');
        return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }

    function addMsg(role, html, timestamp) {
        var cls = role === 'user' ? 'is-user' : 'is-assistant';
        var timeHtml = timestamp ? '<div class="maggy-msg-time">' + esc(formatTime(timestamp)) + '</div>' : '';
        var div = document.createElement('div');
        div.className = 'maggy-message ' + cls;
        // Tool tags, then the read-only trace (S06), then the answer itself, as in
        // the design: what Mago looked up sits above what it concluded.
        div.innerHTML = '<div class="maggy-tool-tags"></div>'
            + '<div class="maggy-tool-trace mago-trace is-tight"></div>'
            + '<div class="maggy-message-content">' + html + '</div>' + timeHtml;
        chat.classList.remove('is-empty');
        msgs.insertBefore(div, loading);
        msgs.scrollTop = msgs.scrollHeight;
        return div;
    }

    // S06: a read-only call is one quiet line above the answer — spinner while
    // it runs, a check when it is done. The lines stay for as long as the answer
    // is on screen, so it is always clear what Mago looked up.
    function updateToolStatus(msgEl, toolName, status, message) {
        if (status === 'running') {
            var el = UI.readLine({text: message || ('Running ' + toolName + '…'), tool: toolName, state: 'active'});
            el.classList.add('maggy-tool-status');
            el.setAttribute('data-tool', toolName);
            var trace = msgEl.querySelector('.maggy-tool-trace');
            if (trace) {
                trace.appendChild(el);
            } else {
                msgEl.querySelector('.maggy-message-content').before(el);
            }
            msgs.scrollTop = msgs.scrollHeight;
        } else if (status === 'done') {
            var statusEl = msgEl.querySelector('.maggy-tool-status[data-tool="' + toolName + '"]:not(.is-done)');
            if (statusEl) {
                statusEl.magoSetState('done');
                statusEl.classList.add('maggy-tool-status', 'mago-readline', 'is-done');
            }
        }
    }

    function addToolTag(msgEl, toolName) {
        var tags = msgEl.querySelector('.maggy-tool-tags');
        if (!tags) return;
        var tag = document.createElement('span');
        tag.className = 'maggy-tool-tag';
        tag.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>'
            + esc(toolName);
        tags.appendChild(tag);
    }

    // Errors land as a callout (W18) under whatever text already streamed.
    function showError(msgEl, text) {
        msgEl.appendChild(UI.callout({tone: 'danger', text: text || 'Unknown error'}));
        msgs.scrollTop = msgs.scrollHeight;
    }

    function showGreeting() {
        chat.classList.add('is-empty');
        setSubtitle('');
    }

    // Welcome starters drop the skill into the input so the slash menu takes over.
    var starters = chat.querySelectorAll('.maggy-starter');
    for (var si = 0; si < starters.length; si++) {
        starters[si].onclick = function() {
            var name = this.getAttribute('data-skill');
            if (!name) return;
            input.value = '/' + name + ' ';
            input.dispatchEvent(new Event('input'));
            input.focus();
        };
    }

    function send() {
        var text = input.value.trim();
        if (!text || busy) return;
        hideSlashMenu();
        input.value = '';
        sendBtn.classList.add('is-idle');
        autoGrow();
        setBusy(true);
        addMsg('user', renderMd(text));

        var msg = null;
        var content = null;
        var full = '';

        fetch(config.streamUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({message:text, conversation_id:conversationId, form_key:formKey}),
            credentials: 'same-origin'
        }).then(function(r) {
            if (!r.ok) {
                throw new Error('HTTP ' + r.status);
            }
            var ct = r.headers.get('content-type') || '';
            if (ct.indexOf('text/event-stream') === -1) {
                return r.text().then(function(t) {
                    throw new Error('Expected SSE but got: ' + ct.substring(0, 50));
                });
            }
            var reader = r.body.getReader();
            var dec = new TextDecoder();
            var buf = '', evt = '';
            var gotDone = false;
            var writeToolDetected = false;

            function processLine(ln) {
                ln = ln.trim();
                if (ln.indexOf('event: ')===0) { evt=ln.substring(7); }
                else if (ln.indexOf('data: ')===0) {
                    try { var d=JSON.parse(ln.substring(6)); } catch(e){return;}
                    if (evt==='text'&&d.text) {
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        full+=d.text; content.innerHTML=renderMd(full); msgs.scrollTop=msgs.scrollHeight;
                    }
                    else if (evt==='conversation') { conversationId=d.conversation_id; saveState(); }
                    else if (evt==='tool_call') {
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        addToolTag(msg, d.name);
                    }
                    else if (evt==='tool_status') {
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        updateToolStatus(msg, d.name, d.status, d.message);
                    }
                    else if (evt==='confirm') {
                        writeToolDetected = true;
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        setBusy(false);
                        showConfirmButtons(msg, conversationId, d.tools || []);
                    }
                    else if (evt==='done') {
                        gotDone = true;
                        if(d.conversation_id) conversationId=d.conversation_id;
                        saveState(); setBusy(false);
                        if (d.pending_confirmation && msg && !writeToolDetected) {
                            showConfirmButtons(msg, d.message_id || conversationId, []);
                        }
                        if (!d.pending_confirmation && writeToolDetected) {
                            writeToolDetected = false;
                        }
                    }
                    else if (evt==='error') {
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        showError(msg, d.error);
                    }
                }
            }

            function read(res) {
                buf += res.done ? dec.decode() : dec.decode(res.value, {stream:true});
                var lines = buf.split('\n'); buf = res.done ? '' : lines.pop();
                lines.forEach(processLine);
                if (res.done || gotDone) {
                    setBusy(false);
                    return;
                }
                return reader.read().then(read);
            }
            return reader.read().then(read);
        }).catch(function(e) {
            setBusy(false);
            if (!msg) { msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
            showError(msg, 'Connection error: ' + e.message);
        });
    }

    // S01: a write action asks first. The card names the skill, lists the
    // parameters it will run with and offers Allow / Not now. Once allowed it
    // turns into the S02 progress card, and when the run finishes into the S03
    // collapsed line; "Not now" leaves a muted line and no call is made.
    function showConfirmButtons(msgEl, messageIdOrConvId, tools) {
        // Prevent duplicate confirm cards
        if (msgEl.querySelector('.maggy-confirm-actions')) return;

        tools = tools || [];
        var first = tools[0] || null;
        var title = first ? skillTitle(first.name) : 'Confirm action';
        var text = first && first.description ? first.description : 'I want to perform an action. Allow this?';
        var card = UI.skillAsk({
            title: title,
            tool: first ? first.name : null,
            text: text,
            params: first ? UI.paramsFromInput(first.input) : [],
            classes: {actions: 'maggy-confirm-actions', allow: 'maggy-btn--confirm', later: 'maggy-btn--reject'},
            onAllow: function() { decide(true); },
            onLater: function() { decide(false); }
        });
        // Several tools in one confirmation: the extra ones add their own parameter tables.
        var actions = card.querySelector('.maggy-confirm-actions');
        tools.slice(1).forEach(function(t) {
            var extra = UI.paramTable([{key: 'tool', value: t.name}].concat(UI.paramsFromInput(t.input)));
            if (extra) actions.parentNode.insertBefore(extra, actions);
        });
        msgEl.appendChild(card);
        msgs.scrollTop = msgs.scrollHeight;

        function decide(allowed) {
            actions.innerHTML = '';
            actions.appendChild(UI.spinner());
            getMessageId(function(mid) {
                if (allowed) {
                    var running = UI.skillRunning({title: title, progress: 30});
                    card.replaceWith(running);
                    handleConfirm(mid, {card: running, title: title, tools: tools, startedAt: Date.now()});
                } else {
                    card.replaceWith(UI.skillLine({title: title, action: first && first.input ? first.input.action : null, state: 'skipped'}));
                    handleReject(mid);
                }
            });
        }

        function getMessageId(callback) {
            // If we already have a message_id from the done event, use it
            if (messageIdOrConvId > 10000) {
                callback(messageIdOrConvId);
                return;
            }
            // Otherwise fetch it from the status endpoint using conversation_id
            fetch(config.statusUrl, {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                body: JSON.stringify({conversation_id: messageIdOrConvId, form_key: formKey}),
                credentials: 'same-origin'
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.message_id) {
                    callback(d.message_id);
                } else {
                    // Retry after 1s — DB write may not be done yet
                    setTimeout(function() { getMessageId(callback); }, 1000);
                }
            }).catch(function() {
                setTimeout(function() { getMessageId(callback); }, 1000);
            });
        }
    }

    // run = {card, title, tools, startedAt}: the S02 card that replaced the
    // question; tool_status events feed its step list and "done" collapses it.
    function handleConfirm(messageId, run) {
        setBusy(true);
        var msg = null, content = null, full = '';

        function finishRun(state) {
            if (!run || !run.card || !run.card.parentNode) return;
            var first = run.tools && run.tools[0];
            var seconds = ((Date.now() - run.startedAt) / 1000).toFixed(1).replace('.', ',') + 's';
            run.card.replaceWith(UI.skillLine({
                title: run.title,
                action: first && first.input ? first.input.action : null,
                duration: seconds,
                state: state,
                request: first ? first.input : undefined
            }));
            run.card = null;
        }

        fetch(config.confirmUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({message_id: messageId, form_key: formKey}),
            credentials: 'same-origin'
        }).then(function(r) {
            var ct = r.headers.get('content-type') || '';
            if (ct.indexOf('text/event-stream') === -1) {
                return r.json().then(function(d) {
                    setBusy(false);
                    finishRun(d.error ? 'failed' : 'done');
                    if (d.error) showError(addMsg('assistant', ''), d.error);
                });
            }
            var reader = r.body.getReader();
            var dec = new TextDecoder();
            var buf = '', evt = '';
            var failed = false;
            var activeStep = null;

            function processLine(ln) {
                ln = ln.trim();
                if (ln.indexOf('event: ')===0) { evt=ln.substring(7); }
                else if (ln.indexOf('data: ')===0) {
                    try { var d=JSON.parse(ln.substring(6)); } catch(e){return;}
                    if (evt==='text'&&d.text) {
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        full+=d.text; content.innerHTML=renderMd(full); msgs.scrollTop=msgs.scrollHeight;
                    }
                    else if (evt==='tool_call') {
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        addToolTag(msg, d.name);
                    }
                    else if (evt==='tool_status') {
                        // The confirmed write reports into the progress card; anything
                        // after it (follow-up reads) goes under the new answer.
                        if (run && run.card) {
                            if (d.status === 'running') {
                                activeStep = run.card.magoAddStep({label: d.message || d.name, state: 'active'});
                            } else if (d.status === 'done' && activeStep) {
                                activeStep.magoSetState('done');
                                activeStep = null;
                                run.card.magoUpdate({progress: 90});
                            }
                        } else {
                            if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                            updateToolStatus(msg, d.name, d.status, d.message);
                        }
                    }
                    else if (evt==='done') {
                        if (d.conversation_id) conversationId=d.conversation_id;
                        saveState(); setBusy(false);
                        finishRun(failed ? 'failed' : 'done');
                        if (d.pending_confirmation && msg) {
                            showConfirmButtons(msg, d.message_id || conversationId, []);
                        }
                    }
                    else if (evt==='error') {
                        failed = true;
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        showError(msg, d.error);
                    }
                }
            }

            function read(res) {
                if (res.done) {
                    buf += dec.decode();
                    if (buf.trim()) {
                        var lines = buf.split('\n');
                        lines.forEach(processLine);
                    }
                    setBusy(false);
                    finishRun(failed ? 'failed' : 'done');
                    return;
                }
                buf += dec.decode(res.value, {stream:true});
                var lines = buf.split('\n'); buf = lines.pop();
                lines.forEach(processLine);
                return reader.read().then(read);
            }
            return reader.read().then(read);
        }).catch(function(e) {
            setBusy(false);
            finishRun('failed');
            showError(addMsg('assistant', ''), 'Error confirming action: ' + e.message);
        });
    }

    function handleReject(messageId) {
        fetch(config.rejectUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({message_id: messageId, form_key: formKey}),
            credentials: 'same-origin'
        }).then(function(r) { return r.json(); }).then(function(d) {
            addMsg('assistant', renderMd('Action rejected. No changes were made.'));
        }).catch(function(e) {
            showError(addMsg('assistant', ''), e.message);
        });
    }

    // Restore state from sessionStorage on page load
    try {
        var wasOpen = sessionStorage.getItem(SS_KEY_OPEN) === '1';
        var savedConv = sessionStorage.getItem(SS_KEY_CONV);
        var wasFullsize = sessionStorage.getItem(SS_KEY_FULL) === '1';
        if (wasOpen) {
            if (savedConv) {
                conversationId = parseInt(savedConv, 10) || null;
                if (conversationId) {
                    loadConversation(conversationId);
                }
            }
            if (wasFullsize) {
                chat.classList.add('is-fullsize');
                document.body.classList.add('maggy-fullsize');
                var expandBtn = qs('#maggy-expand');
                expandBtn.title = 'Side panel';
                expandBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
            }
            openPanel();
        }
    } catch(e) {}
})();
