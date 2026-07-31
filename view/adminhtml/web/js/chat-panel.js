(function() {
    var config = window.MAGGY_CONFIG;
    var adminUser = config.adminUser;
    var assistantName = config.assistantName;
    var formKey = config.formKey;
    var skills = config.skills;
    var conversationId = null;
    var busy = false;
    var showingHistory = false;
    var slashActive = false;
    var slashIndex = 0;
    var filteredSkills = [];
    var SS_KEY_OPEN = 'maggy_open';
    var SS_KEY_CONV = 'maggy_conv';

    function saveState() {
        try {
            sessionStorage.setItem(SS_KEY_OPEN, chat.classList.contains('is-open') ? '1' : '0');
            sessionStorage.setItem(SS_KEY_CONV, conversationId ? String(conversationId) : '');
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

    function clearMsgs() {
        while (msgs.firstChild) {
            if (msgs.firstChild === loading) break;
            msgs.removeChild(msgs.firstChild);
        }
        while (loading.nextSibling) {
            msgs.removeChild(loading.nextSibling);
        }
        loading.style.display = 'none';
    }

    function openPanel() {
        chat.classList.add('is-open');
        document.body.classList.add('maggy-active');
        if (msgs.children.length <= 1 && !conversationId) {
            showGreeting();
        }
        saveState();
        input.focus();
    }

    function closePanel() {
        chat.classList.remove('is-open');
        document.body.classList.remove('maggy-active');
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

    // Slash command logic
    function showSlashMenu(filter) {
        var q = (filter || '').toLowerCase();
        filteredSkills = skills.filter(function(s) {
            return !q || s.name.indexOf(q) !== -1 || s.description.toLowerCase().indexOf(q) !== -1;
        });
        if (!filteredSkills.length) {
            hideSlashMenu();
            return;
        }
        slashIndex = 0;
        slashActive = true;
        slashMenu.innerHTML = '';
        filteredSkills.forEach(function(s, i) {
            var div = document.createElement('div');
            div.className = 'maggy-slash-item' + (i === 0 ? ' is-active' : '');
            var badge = s.readOnly
                ? '<span class="maggy-slash-item-badge maggy-slash-item-badge--read">read</span>'
                : '<span class="maggy-slash-item-badge maggy-slash-item-badge--write">write</span>';
            var shortDesc = s.description.length > 60 ? s.description.substring(0, 60) + '...' : s.description;
            div.innerHTML = '<span class="maggy-slash-item-name">/' + esc(s.name) + '</span>'
                + '<span class="maggy-slash-item-desc">' + esc(shortDesc) + '</span>'
                + badge;
            div.onclick = function() { selectSlashSkill(s); };
            slashMenu.appendChild(div);
        });
        slashMenu.classList.add('is-visible');
    }

    function hideSlashMenu() {
        slashActive = false;
        slashMenu.classList.remove('is-visible');
        slashMenu.innerHTML = '';
    }

    function selectSlashSkill(skill) {
        input.value = 'Use the ' + skill.name + ' skill to ';
        hideSlashMenu();
        input.focus();
    }

    function updateSlashHighlight() {
        var items = slashMenu.querySelectorAll('.maggy-slash-item');
        items.forEach(function(el, i) {
            el.classList.toggle('is-active', i === slashIndex);
        });
        if (items[slashIndex]) items[slashIndex].scrollIntoView({block:'nearest'});
    }

    input.addEventListener('input', function() {
        var v = input.value;
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
                slashIndex = Math.min(filteredSkills.length - 1, slashIndex + 1);
                updateSlashHighlight();
                return;
            }
            if (e.keyCode === 13 || e.keyCode === 9) { // enter or tab
                e.preventDefault();
                if (filteredSkills[slashIndex]) selectSlashSkill(filteredSkills[slashIndex]);
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
                    loadConversation(conv.entity_id);
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

    function loadConversation(id) {
        hideHistory();
        clearMsgs();
        lastDateLabel = '';
        conversationId = id;

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
            (data.messages || []).forEach(function(m) {
                if (m.role === 'user' || m.role === 'assistant') {
                    addDateSep(m.created_at);
                    addMsg(m.role, renderMd(m.content || ''), m.created_at);
                }
            });
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
        var label = role === 'user' ? (adminUser || 'You') : assistantName;
        var timeHtml = timestamp ? '<div class="maggy-msg-time">' + esc(formatTime(timestamp)) + '</div>' : '';
        var div = document.createElement('div');
        div.className = 'maggy-message ' + cls;
        div.innerHTML = '<div class="maggy-message-role">' + label + '</div>'
            + '<div class="maggy-tool-tags"></div>'
            + '<div class="maggy-message-content">' + html + '</div>' + timeHtml;
        msgs.insertBefore(div, loading);
        msgs.scrollTop = msgs.scrollHeight;
        return div;
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

    function formatConfirmMessage(tools) {
        if (!tools || !tools.length) return 'I want to perform an action. Allow this?';
        var parts = [];
        tools.forEach(function(t) {
            var line = '**' + t.name + '**';
            if (t.input) {
                var params = Object.keys(t.input).map(function(k) {
                    return k + ': `' + t.input[k] + '`';
                });
                if (params.length) line += ' — ' + params.join(', ');
            }
            parts.push(line);
        });
        return 'I want to perform the following action:\n\n' + parts.join('\n') + '\n\nAllow this?';
    }

    function showGreeting() {
        var name = adminUser || 'there';
        addMsg('assistant', renderMd("Hi " + name + "! How can I help you with your store today? Type / to see available skills."));
    }

    function send() {
        var text = input.value.trim();
        if (!text || busy) return;
        hideSlashMenu();
        input.value = '';
        busy = true;
        loading.style.display = '';
        sendBtn.disabled = true;
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
                    else if (evt==='confirm') {
                        writeToolDetected = true;
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        var desc = formatConfirmMessage(d.tools || []);
                        content.innerHTML = renderMd(desc);
                        busy=false; loading.style.display='none'; sendBtn.disabled=false;
                        showConfirmButtons(msg, conversationId);
                    }
                    else if (evt==='done') {
                        gotDone = true;
                        if(d.conversation_id) conversationId=d.conversation_id;
                        saveState(); busy=false; loading.style.display='none'; sendBtn.disabled=false;
                        if (d.pending_confirmation && msg && !writeToolDetected) {
                            showConfirmButtons(msg, d.message_id || conversationId);
                        }
                        if (!d.pending_confirmation && writeToolDetected) {
                            writeToolDetected = false;
                        }
                    }
                    else if (evt==='error') {
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        full+='\n\nError: '+(d.error||'Unknown'); content.innerHTML=renderMd(full);
                    }
                }
            }

            function read(res) {
                buf += res.done ? dec.decode() : dec.decode(res.value, {stream:true});
                var lines = buf.split('\n'); buf = res.done ? '' : lines.pop();
                lines.forEach(processLine);
                if (res.done || gotDone) {
                    busy=false; loading.style.display='none'; sendBtn.disabled=false;
                    return;
                }
                return reader.read().then(read);
            }
            return reader.read().then(read);
        }).catch(function(e) {
            busy=false; loading.style.display='none'; sendBtn.disabled=false;
            if (!msg) { msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
            content.innerHTML='<em>Connection error: '+esc(e.message)+'</em>';
        });
    }
    function showConfirmButtons(msgEl, messageIdOrConvId) {
        // Prevent duplicate confirm buttons
        if (msgEl.querySelector('.maggy-confirm-actions')) return;

        var actions = document.createElement('div');
        actions.className = 'maggy-confirm-actions';
        actions.innerHTML = '<button type="button" class="maggy-btn maggy-btn--confirm">Confirm</button>'
            + '<button type="button" class="maggy-btn maggy-btn--reject">Reject</button>';
        msgEl.appendChild(actions);
        msgs.scrollTop = msgs.scrollHeight;

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

        actions.querySelector('.maggy-btn--confirm').onclick = function() {
            actions.innerHTML = '<span style="color:#999;font-size:12px;">Processing...</span>';
            getMessageId(function(mid) {
                actions.remove();
                handleConfirm(mid);
            });
        };
        actions.querySelector('.maggy-btn--reject').onclick = function() {
            actions.innerHTML = '<span style="color:#999;font-size:12px;">Processing...</span>';
            getMessageId(function(mid) {
                actions.remove();
                handleReject(mid);
            });
        };
    }

    function handleConfirm(messageId) {
        busy = true;
        loading.style.display = '';
        sendBtn.disabled = true;
        var msg = null, content = null, full = '';

        fetch(config.confirmUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({message_id: messageId, form_key: formKey}),
            credentials: 'same-origin'
        }).then(function(r) {
            var ct = r.headers.get('content-type') || '';
            if (ct.indexOf('text/event-stream') === -1) {
                return r.json().then(function(d) {
                    busy=false; loading.style.display='none'; sendBtn.disabled=false;
                    if (d.error) addMsg('assistant', renderMd('Error: ' + d.error));
                });
            }
            var reader = r.body.getReader();
            var dec = new TextDecoder();
            var buf = '', evt = '';

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
                    else if (evt==='done') { busy=false; loading.style.display='none'; sendBtn.disabled=false; }
                    else if (evt==='error') {
                        if (!msg) { loading.style.display='none'; msg=addMsg('assistant',''); content=msg.querySelector('.maggy-message-content'); }
                        full+='\n\nError: '+(d.error||'Unknown'); content.innerHTML=renderMd(full);
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
                    busy=false; loading.style.display='none'; sendBtn.disabled=false;
                    return;
                }
                buf += dec.decode(res.value, {stream:true});
                var lines = buf.split('\n'); buf = lines.pop();
                lines.forEach(processLine);
                return reader.read().then(read);
            }
            return reader.read().then(read);
        }).catch(function(e) {
            busy=false; loading.style.display='none'; sendBtn.disabled=false;
            addMsg('assistant', renderMd('Error confirming action: ' + e.message));
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
            addMsg('assistant', renderMd('Error: ' + e.message));
        });
    }

    // Restore state from sessionStorage on page load
    try {
        var wasOpen = sessionStorage.getItem(SS_KEY_OPEN) === '1';
        var savedConv = sessionStorage.getItem(SS_KEY_CONV);
        if (wasOpen) {
            if (savedConv) {
                conversationId = parseInt(savedConv, 10) || null;
                if (conversationId) {
                    loadConversation(conversationId);
                }
            }
            openPanel();
        }
    } catch(e) {}
})();
