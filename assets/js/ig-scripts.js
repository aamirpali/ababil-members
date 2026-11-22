/* Ababil Members — scoped IG-style interactions. No globals leaked. */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    var containers = document.querySelectorAll('.ig-plugin-container');

    containers.forEach(function (root) {
      if (root.querySelector('.ig-error')) { root.classList.add('ig-editing'); }
      var ajaxUrl = root.getAttribute('data-ajax') || '';
      var followNonce = root.getAttribute('data-follow-nonce') || '';
      var socialNonce = root.getAttribute('data-social-nonce') || '';

      function socialRequest(action, fields) {
        var fd = new FormData(); fd.append('action', action); fd.append('nonce', socialNonce);
        Object.keys(fields || {}).forEach(function (key) { fd.append(key, fields[key]); });
        return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (r) { return r.json(); });
      }

      root.querySelectorAll('.ig-composer-open').forEach(function (b) { b.addEventListener('click', function () { var c = b.closest('.ig-composer'); c.classList.add('is-open'); c.querySelector('.ig-composer-input').focus(); }); });

      function bindPostForm(form, listSelector, action) {
        if (!form) return;
        form.addEventListener('submit', function (e) {
          e.preventDefault(); var input = form.querySelector('[name="body"]');
          // The file input carries name="image", so new FormData(form) already
          // includes it — do NOT append it again (that would send two files).
          var fd = new FormData(form); fd.append('action', action); fd.append('nonce', socialNonce);
          var gid = form.getAttribute('data-group'); if (gid) fd.append('group_id', gid);
          var msg = form.querySelector('.ig-composer-msg');
          if (msg) { msg.hidden = true; msg.textContent = ''; }
          fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              if (j.success) {
                var list = root.querySelector(listSelector); var empty = list.querySelector('.ig-empty'); if (empty) empty.remove();
                list.insertAdjacentHTML('afterbegin', j.data.html); input.value = '';
                input.dispatchEvent(new Event('input')); // refresh the character counter
                form.classList.remove('is-open');
                var image = form.querySelector('input[type="file"]'); if (image) image.value = '';
                var pv = form.querySelector('.ig-post-preview');
                if (pv) { pv.hidden = true; var pi = pv.querySelector('.ig-post-preview-img'); if (pi) pi.removeAttribute('src'); }
                var fv = form.querySelector('.ig-feeling-value'); if (fv) fv.value = '';
                var fl = form.querySelector('.ig-feeling-btn-label'); if (fl) fl.textContent = 'Feeling';
              } else if (msg && j.data && j.data.message) {
                msg.textContent = j.data.message; msg.hidden = false;
              }
            })
            .catch(function () { if (msg) { msg.textContent = 'Could not post. Please try again.'; msg.hidden = false; } });
        });
      }
      bindPostForm(root.querySelector('#ig-post-form'), '#ig-feed-list', 'am_post_create');
      var gpost = root.querySelector('#ig-group-post-form');
      if (gpost) { gpost.setAttribute('data-group', root.getAttribute('data-group-id')); bindPostForm(gpost, '#ig-group-feed', 'am_group_post'); }

      /* ---- Feed photo: live preview + remove + char counter ---- */
      (function () {
        var form = root.querySelector('#ig-post-form');
        if (!form) return;
        var cInput = form.querySelector('.ig-composer-input');
        var cCount = form.querySelector('.ig-composer-count');
        if (cInput && cCount) {
          var refreshCount = function () { cCount.textContent = cInput.value.length + ' / 2000'; };
          cInput.addEventListener('input', refreshCount);
          refreshCount();
        }
        var file = form.querySelector('#ig-post-image');
        var pv = form.querySelector('.ig-post-preview');
        var pvImg = pv ? pv.querySelector('.ig-post-preview-img') : null;
        var rm = pv ? pv.querySelector('.ig-post-preview-remove') : null;
        if (!file || !pv || !pvImg) return;
        var objectUrl = null;
        function clearPreview() {
          file.value = '';
          pv.hidden = true;
          pvImg.removeAttribute('src');
          if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        }
        file.addEventListener('change', function () {
          var f = file.files && file.files[0];
          if (!f) { clearPreview(); return; }
          if (objectUrl) URL.revokeObjectURL(objectUrl);
          objectUrl = URL.createObjectURL(f);
          pvImg.src = objectUrl;
          pv.hidden = false;
        });
        if (rm) rm.addEventListener('click', clearPreview);
      })();

      root.addEventListener('click', function (e) {
        var like = e.target.closest('.ig-like-btn');
        var join = e.target.closest('.ig-group-join');
        var cToggle = e.target.closest('.ig-comment-toggle');
        var feeling = e.target.closest('.ig-composer-feeling');
        var cLike = e.target.closest('.ig-comment-like');
        var reply = e.target.closest('.ig-reply-btn');
        var share = e.target.closest('.ig-share-btn');
        if (like) socialRequest('am_post_like', { post_id: like.getAttribute('data-post') }).then(function (j) { if (j.success) { like.classList.toggle('is-liked', j.data.liked); var card = like.closest('.ig-post-card'); if (card) card.querySelector('.ig-like-count').textContent = j.data.count; } });
        if (cLike) socialRequest('am_comment_like', { comment_id: cLike.getAttribute('data-comment') }).then(function (j) { if (j.success) { cLike.classList.toggle('is-liked', j.data.liked); cLike.querySelector('.ig-comment-like-count').textContent = j.data.count; } });
        if (reply) { var wrap = reply.closest('.ig-comment'); var form = wrap.querySelector(':scope > .ig-comment-main > .ig-reply-form'); if (form) { form.hidden = false; var inp = form.querySelector('[name="body"]'); if (inp && inp.value.indexOf('@' + reply.getAttribute('data-user')) !== 0) { inp.value = '@' + reply.getAttribute('data-user') + ' '; } inp.focus(); } }
        if (share) {
          var pid = share.getAttribute('data-post');
          var link = location.origin + location.pathname + '?am_post=' + pid;
          if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(link).catch(function(){});
          var old = share.innerHTML; share.innerHTML = '✓ Copied!';
          setTimeout(function(){ share.innerHTML = old; }, 1800);
        }
        if (join) socialRequest('am_group_join', { group_id: join.getAttribute('data-group') }).then(function (j) {
          if (!j.success) return;
          var now = !!j.data.joined; join.setAttribute('data-joined', now ? '1' : '0');
          join.textContent = now ? '✓ Joined' : 'Join group';
          join.classList.toggle('ig-btn-ghost', now); join.classList.toggle('ig-btn-primary', !now);
          root.querySelectorAll('[data-group-count="' + join.getAttribute('data-group') + '"]').forEach(function (c) { c.textContent = '👤 ' + j.data.count + ' members'; });
          if (root.classList.contains('ig-groups-root') && root.querySelector('.ig-group-locked') && now) window.location.reload();
        });
        if (cToggle) { var card2 = cToggle.closest('.ig-post-card'); var list = card2.querySelector('.ig-comment-list'); if (list) list.classList.toggle('is-open'); var inp = card2.querySelector('.ig-comment-input'); if (inp) inp.focus(); }
        if (feeling) {
          var fwrap = feeling.closest('.ig-feeling-wrap');
          var fpick = fwrap ? fwrap.querySelector('.ig-feeling-picker') : null;
          if (fpick) fpick.hidden = !fpick.hidden;
        }
        var fopt = e.target.closest('.ig-feeling-option');
        if (fopt) {
          var fwrap2 = fopt.closest('.ig-feeling-wrap');
          if (fwrap2) {
            var fpick2 = fwrap2.querySelector('.ig-feeling-picker');
            if (fpick2) fpick2.hidden = true;
            var emoji = fopt.querySelector('span') ? fopt.querySelector('span').textContent : '';
            var label = fopt.querySelector('small') ? fopt.querySelector('small').textContent : '';
            var flabel = fwrap2.querySelector('.ig-feeling-btn-label');
            if (flabel) flabel.textContent = emoji + ' ' + label;
            var fhidden = fwrap2.querySelector('.ig-feeling-value');
            if (fhidden) fhidden.value = fopt.getAttribute('data-feeling');
          }
        }
        if (!e.target.closest('.ig-feeling-wrap')) {
          root.querySelectorAll('.ig-feeling-picker').forEach(function (p) { p.hidden = true; });
        }
      });
      root.querySelectorAll('.ig-comment-form').forEach(function (form) { form.addEventListener('submit', function (e) {
        e.preventDefault(); var body = form.querySelector('[name="body"]');
        var card = form.closest('.ig-post-card');
        var fields = { body: body.value };
        var postId = form.querySelector('[name="post_id"]'); fields.post_id = postId ? postId.value : (card ? card.getAttribute('data-post') : '');
        var parentId = form.querySelector('[name="parent_id"]'); if (parentId) fields.parent_id = parentId.value;
        socialRequest('am_comment_add', fields).then(function (j) {
          if (!j.success) return;
          if (j.data.parent) {
            var holder = card.querySelector('[data-comment="' + j.data.parent + '"] > .ig-comment-main > .ig-comment-replies');
            if (!holder) { var pc = card.querySelector('[data-comment="' + j.data.parent + '"] > .ig-comment-main'); holder = document.createElement('div'); holder.className = 'ig-comment-replies'; pc.appendChild(holder); }
            holder.insertAdjacentHTML('beforeend', j.data.html); form.hidden = true;
          } else {
            var list = card.querySelector('.ig-comment-list'); list.classList.add('is-open'); list.insertAdjacentHTML('beforeend', j.data.html);
          }
          body.value = '';
        });
      }); });
      root.querySelectorAll('.ig-review-form').forEach(function (form) { form.addEventListener('submit', function (e) { e.preventDefault(); socialRequest('am_review_add', { target_id: form.querySelector('[name="target_id"]').value, rating: form.querySelector('[name="rating"]').value, body: form.querySelector('[name="body"]').value }).then(function (j) { if (j.success) { form.reset(); window.location.reload(); } }); }); });
      root.querySelectorAll('.ig-group-create-form').forEach(function (form) { form.addEventListener('submit', function (e) { e.preventDefault(); socialRequest('am_group_create', { name: form.querySelector('[name="name"]').value, description: form.querySelector('[name="description"]').value, privacy: form.querySelector('[name="privacy"]').value }).then(function (j) { if (j.success) { window.location.reload(); } }); }); });

      /* ---- Profile tabs ---- */
      var tabs = root.querySelectorAll('.ig-tab');
      if (tabs.length) {
        tabs.forEach(function (tab) {
          tab.addEventListener('click', function () {
            var target = tab.getAttribute('data-tab');
            tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            root.querySelectorAll('.ig-tab-panel').forEach(function (panel) {
              panel.classList.toggle('is-active', panel.getAttribute('data-panel') === target);
            });
          });
        });
      }

      /* ---- Edit profile toggle ---- */
      var editBtn = root.querySelector('.ig-edit-toggle');
      var editPanel = root.querySelector('.ig-edit-panel');
      if (editBtn && editPanel) {
        editBtn.addEventListener('click', function (e) {
          e.preventDefault();
          var open = editPanel.classList.toggle('is-open');
          root.classList.toggle('ig-editing', open);
        });
      }

      /* ---- Image previews (optional uploads) ---- */
      function previewFile(inputId, targetClass, isAvatar) {
        var input = root.querySelector('#' + inputId);
        if (!input) { return; }
        input.addEventListener('change', function () {
          if (!input.files || !input.files[0]) { return; }
          var url = URL.createObjectURL(input.files[0]);
          var target = root.querySelector(targetClass);
          if (!target) {
            target = document.createElement('img');
            target.className = isAvatar ? 'ig-avatar-img' : 'ig-cover-img';
            var holder = isAvatar ? root.querySelector('.ig-avatar-inner') : root.querySelector('.ig-cover');
            if (holder) { holder.appendChild(target); }
          }
          target.src = url;
        });
      }
      previewFile('ig-avatar-file', '.ig-avatar-img', true);
      // Cover preview is handled by the crop stage only — never swap the live cover with a blob URL.

      /* ---- Cover crop drag (object-position) ---- */
      var cropWrap = root.querySelector('#ig-crop-wrap');
      var cropStage = root.querySelector('#ig-crop-stage');
      var cropImg = root.querySelector('#ig-crop-img');
      var posX = root.querySelector('#ig-cover-pos-x');
      var posY = root.querySelector('#ig-cover-pos-y');
      var coverImg = root.querySelector('.ig-cover-img');

      function applyCoverPosition() {
        var x = posX ? parseInt(posX.value, 10) : 50;
        var y = posY ? parseInt(posY.value, 10) : 50;
        if (isNaN(x)) { x = 50; }
        if (isNaN(y)) { y = 50; }
        if (cropImg) { cropImg.style.objectPosition = x + '% ' + y + '%'; }
        if (coverImg) { coverImg.style.objectPosition = x + '% ' + y + '%'; }
      }

      var coverInput = root.querySelector('#ig-cover-file');
      if (coverInput && cropWrap) {
        coverInput.addEventListener('change', function () {
          if (cropImg && coverInput.files && coverInput.files[0]) {
            cropImg.src = URL.createObjectURL(coverInput.files[0]);
            cropWrap.classList.add('is-visible');
            if (posX) { posX.value = 50; }
            if (posY) { posY.value = 50; }
            applyCoverPosition();
          }
        });
      }

      if (cropStage) {
        var dragging = false;
        var startX = 0, startY = 0, startPX = 50, startPY = 50;
        cropStage.addEventListener('pointerdown', function (e) {
          if (!cropImg || !cropImg.src) { return; }
          dragging = true;
          startX = e.clientX;
          startY = e.clientY;
          startPX = posX ? parseInt(posX.value, 10) : 50;
          startPY = posY ? parseInt(posY.value, 10) : 50;
          cropStage.setPointerCapture(e.pointerId);
        });
        cropStage.addEventListener('pointermove', function (e) {
          if (!dragging) { return; }
          var rect = cropStage.getBoundingClientRect();
          var nx = startPX - ((e.clientX - startX) / rect.width) * 100;
          var ny = startPY - ((e.clientY - startY) / rect.height) * 100;
          nx = Math.max(0, Math.min(100, nx));
          ny = Math.max(0, Math.min(100, ny));
          if (posX) { posX.value = Math.round(nx); }
          if (posY) { posY.value = Math.round(ny); }
          applyCoverPosition();
        });
        cropStage.addEventListener('pointerup', function () { dragging = false; });
        cropStage.addEventListener('pointercancel', function () { dragging = false; });
      }
      applyCoverPosition();

      /* ---- Follow / Unfollow (profile + members) ---- */
      root.querySelectorAll('.ig-follow-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!ajaxUrl || !followNonce || btn.disabled) { return; }
          var isFollowing = btn.getAttribute('data-following') === '1';
          var fd = new FormData();
          fd.append('action', isFollowing ? 'am_unfollow' : 'am_follow');
          fd.append('nonce', followNonce);
          fd.append('user_id', btn.getAttribute('data-user'));
          btn.disabled = true;
          fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              if (!j.success) { return; }
              var on = !!j.data.following;
              btn.setAttribute('data-following', on ? '1' : '0');
              btn.textContent = on ? 'Following' : 'Follow';
              btn.classList.toggle('is-following', on);
              var countEl = root.querySelector('.ig-stat-followers .ig-stat-num');
              if (countEl && j.data.counts) { countEl.textContent = j.data.counts.followers; }
            })
            .catch(function () {})
            .then(function () { btn.disabled = false; });
        });
      });

      /* ---- Messages: mobile split-pane ---- */
      var messages = root.querySelector('.ig-messages');
      if (messages) {
        var params = new URLSearchParams(window.location.search);
        if (params.has('to')) { messages.classList.add('is-chat-open'); }
        root.querySelectorAll('.ig-chat-item').forEach(function (item) {
          item.addEventListener('click', function () {
            root.querySelectorAll('.ig-chat-item').forEach(function (i) { i.classList.remove('is-active'); });
            item.classList.add('is-active');
            messages.classList.add('is-chat-open');
            var title = root.querySelector('.ig-chat-head-name');
            if (title) { title.textContent = item.getAttribute('data-name') || ''; }
            var input = root.querySelector('.ig-chat-input');
            if (input) { input.focus(); }
          });
        });
        var backBtn = root.querySelector('.ig-back-btn');
        if (backBtn) {
          backBtn.addEventListener('click', function () {
            messages.classList.remove('is-chat-open');
          });
        }
      }

      /* ---- Messenger-like chat: AJAX, typing, statuses, emoji, image, report ---- */
      var chatForm = root.querySelector('#ig-chat-form');
      var chatOther = parseInt(root.getAttribute('data-other'), 10) || 0;
      var chatNonce = root.getAttribute('data-chat-nonce') || '';
      if (chatForm && chatOther && ajaxUrl) {
        var chatBody = root.querySelector('#ig-chat-body');
        var chatInput = root.querySelector('#ig-chat-input');
        var typingEl = root.querySelector('#ig-typing');
        var lastId = parseInt(root.getAttribute('data-last-id'), 10) || 0;
        var typingSent = 0;

        function scrollBottom() { chatBody.scrollTop = chatBody.scrollHeight; }
        function chatNotice(msg) {
          var n = document.createElement('div');
          n.className = 'ig-notice';
          n.textContent = msg;
          chatBody.insertBefore(n, chatBody.firstChild);
          setTimeout(function () { if (n.parentNode) { n.parentNode.removeChild(n); } }, 4000);
        }
        function appendHtml(html) {
          if (!html) { return; }
          var tmp = document.createElement('div');
          tmp.innerHTML = html;
          while (tmp.firstChild) {
            var node = tmp.firstChild;
            var nodeId = node.getAttribute && node.getAttribute('data-id');
            if (nodeId && chatBody.querySelector('.ig-bubble-row[data-id="' + nodeId + '"]')) {
              tmp.removeChild(node);
              continue;
            }
            chatBody.insertBefore(node, typingEl);
          }
          scrollBottom();
        }
        function applyStatuses(list) {
          (list || []).forEach(function (s) {
            var el = chatBody.querySelector('.ig-bubble-row[data-id="' + s.id + '"] .ig-msg-status');
            if (!el) { return; }
            el.textContent = s.status === 'seen' ? 'Seen ✓✓' : (s.status === 'sent' ? 'Sent ✓' : 'Delivered ✓✓');
            el.className = 'ig-msg-status ig-status-' + s.status;
          });
        }
        function pollChat() {
          var fd = new FormData();
          fd.append('action', 'am_chat_poll');
          fd.append('nonce', chatNonce);
          fd.append('other_id', chatOther);
          fd.append('last_id', lastId);
          fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              if (!j.success) { return; }
              appendHtml(j.data.html);
              if (j.data.last_id > lastId) { lastId = j.data.last_id; }
              applyStatuses(j.data.statuses);
              if (typingEl) { typingEl.hidden = !j.data.typing; }
            })
            .catch(function () {});
        }
        setInterval(pollChat, 3000);
        scrollBottom();

        chatForm.addEventListener('submit', function (e) {
          e.preventDefault();
          var body = chatInput.value.trim();
          if (!body) { return; }
          var fd = new FormData();
          fd.append('action', 'am_chat_send');
          fd.append('nonce', chatNonce);
          fd.append('other_id', chatOther);
          fd.append('body', body);
          chatInput.value = '';
          fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              if (!j.success) { chatNotice(j.data && j.data.message ? j.data.message : 'Message failed.'); return; }
              appendHtml(j.data.row);
              if (j.data.id > lastId) { lastId = j.data.id; }
              if (typingEl) { typingEl.hidden = true; }
            })
            .catch(function () { chatNotice('Message failed. Please retry.'); });
        });

        chatInput.addEventListener('input', function () {
          var now = Date.now();
          if (now - typingSent < 1800) { return; }
          typingSent = now;
          var fd = new FormData();
          fd.append('action', 'am_chat_typing');
          fd.append('nonce', chatNonce);
          fd.append('other_id', chatOther);
          fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd }).catch(function () {});
        });

        var emojiToggle = root.querySelector('.ig-emoji-toggle');
        var emojiPanel = root.querySelector('#ig-emoji-panel');
        if (emojiToggle && emojiPanel) {
          emojiToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            emojiPanel.hidden = !emojiPanel.hidden;
          });
          document.addEventListener('click', function (e) {
            if (emojiPanel.hidden) { return; }
            if (emojiPanel.contains(e.target) || emojiToggle.contains(e.target)) { return; }
            emojiPanel.hidden = true;
          });
          document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { emojiPanel.hidden = true; }
          });
          emojiPanel.addEventListener('click', function (e) { e.stopPropagation(); });
          emojiPanel.querySelectorAll('.ig-emoji').forEach(function (b) {
            b.addEventListener('click', function () {
              var em = b.getAttribute('data-emoji') || '';
              var s = chatInput.selectionStart || chatInput.value.length;
              var en = chatInput.selectionEnd || s;
              chatInput.value = chatInput.value.slice(0, s) + em + chatInput.value.slice(en);
              chatInput.focus();
              chatInput.setSelectionRange(s + em.length, s + em.length);
              emojiPanel.hidden = true;
            });
          });
        }

        var imageInput = root.querySelector('#ig-chat-image');
        if (imageInput) {
          imageInput.addEventListener('change', function () {
            var f = imageInput.files[0];
            if (!f) { return; }
            if (f.size > 1024 * 1024) { chatNotice('Image must be 1 MB or smaller.'); imageInput.value = ''; return; }
            var fd = new FormData();
            fd.append('action', 'am_chat_send');
            fd.append('nonce', chatNonce);
            fd.append('other_id', chatOther);
            fd.append('image', f);
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
              .then(function (r) { return r.json(); })
              .then(function (j) {
                if (!j.success) { chatNotice(j.data && j.data.message ? j.data.message : 'Upload failed.'); }
                else { appendHtml(j.data.row); if (j.data.id > lastId) { lastId = j.data.id; } }
                imageInput.value = '';
              })
              .catch(function () { chatNotice('Upload failed.'); imageInput.value = ''; });
          });
        }

        var reportBtn = root.querySelector('.ig-report-btn');
        if (reportBtn) {
          reportBtn.addEventListener('click', function () {
            if (!window.confirm('Report this conversation?')) { return; }
            var fd = new FormData();
            fd.append('action', 'am_chat_report');
            fd.append('nonce', chatNonce);
            fd.append('other_id', chatOther);
            fd.append('reason', 'Reported from chat');
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
              .then(function (r) { return r.json(); })
              .then(function (j) { chatNotice(j.data && j.data.message ? j.data.message : 'Report submitted.'); })
              .catch(function () {});
          });
        }
      }

      /* ---- Phase 4: password visibility toggles ---- */
      root.querySelectorAll('.ig-pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var wrap = btn.closest('.ig-pw-wrap');
          var input = wrap ? wrap.querySelector('input[type="password"], input[type="text"]') : null;
          if (!input) return;
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.textContent = show ? '🙈' : '👁';
        });
      });

      /* ---- Phase 4: conditional custom fields ---- */
      var depFields = root.querySelectorAll('[data-ig-field][data-depends-on]');
      if (depFields.length) {
        function refreshConditional() {
          root.querySelectorAll('[data-ig-field][data-depends-on]').forEach(function (row) {
            var depSlug = row.getAttribute('data-depends-on');
            var want = row.getAttribute('data-depends-value') || '';
            var source = root.querySelector('[name="' + depSlug + '"]');
            var value = source ? (source.value || '') : '';
            var visible = want === '' ? value !== '' : value === want;
            row.hidden = !visible;
            var control = row.querySelector('input, select, textarea');
            if (control) control.disabled = !visible;
          });
        }
        root.addEventListener('change', refreshConditional);
        root.addEventListener('input', refreshConditional);
        refreshConditional();
      }

      /* ---- Notifications bell ---- */
      var notifBtn   = root.querySelector('#ig-notif-btn');
      var notifPanel = root.querySelector('.ig-notif-panel');
      var notifList  = root.querySelector('[data-notif-list]');
      var notifBadge = root.querySelector('[data-notif-count]');
      if (notifBtn && notifPanel) {
        function setBadge(n) {
          if (!notifBadge) return;
          notifBadge.textContent = n;
          if (n > 0) notifBadge.removeAttribute('hidden');
          else notifBadge.setAttribute('hidden', 'hidden');
        }
        notifBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          var willOpen = notifPanel.hidden;
          notifPanel.hidden = !willOpen;
          if (willOpen && notifList) socialRequest('am_notifications', {}).then(function (j) {
            if (j.success) { notifList.innerHTML = j.data.html; setBadge(j.data.count); }
          });
        });
        // Mark a single notification read, or follow its link after marking.
        function markOne(id, item) {
          if (!id) return;
          socialRequest('am_notifications_read_one', { id: id }).then(function (j) {
            if (!j.success) return;
            setBadge(j.data.count);
            if (item) {
              item.classList.remove('is-unread');
              var b = item.querySelector('.ig-notif-read'); if (b) b.hidden = true;
            }
          });
        }
        notifPanel.addEventListener('click', function (e) {
          e.stopPropagation();
          var readBtn = e.target.closest('.ig-notif-read');
          if (readBtn) {
            e.preventDefault();
            var item = readBtn.closest('.ig-notif-item');
            markOne(readBtn.getAttribute('data-id'), item);
            return;
          }
          var link = e.target.closest('.ig-notif-link');
          if (link) {
            var row = link.closest('.ig-notif-item');
            if (row && row.classList.contains('is-unread')) {
              e.preventDefault();
              var href = link.getAttribute('href');
              socialRequest('am_notifications_read_one', { id: row.getAttribute('data-id') })
                .then(function () { location.href = href; })
                .catch(function () { location.href = href; });
            }
          }
        });
        document.addEventListener('click', function () { if (notifPanel) notifPanel.hidden = true; });
        var readAll = root.querySelector('.ig-notif-readall');
        if (readAll) readAll.addEventListener('click', function () {
          socialRequest('am_notifications_read_all', {}).then(function (j) {
            if (j.success) {
              setBadge(0);
              if (notifList) {
                notifList.querySelectorAll('.ig-notif-item.is-unread').forEach(function (el) { el.classList.remove('is-unread'); });
                notifList.querySelectorAll('.ig-notif-read').forEach(function (el) { el.hidden = true; });
                notifList.querySelectorAll('.ig-notif-empty').forEach(function (el) { el.remove(); });
              }
            }
          });
        });
        // Poll for new notifications so the badge stays current without a reload.
        setInterval(function () {
          if (!root.isConnected) return;
          socialRequest('am_notifications', {}).then(function (j) {
            if (!j.success) return;
            setBadge(j.data.count);
            if (notifPanel && !notifPanel.hidden && notifList) notifList.innerHTML = j.data.html;
          }).catch(function () {});
        }, 30000);

        /* ---- Dark mode toggle ---- */
        var darkBtn = root.querySelector('#ig-dark-btn');
        function applyDark(on) {
          if (on) {
            root.classList.add('ig-dark-mode');
            if (darkBtn) darkBtn.textContent = '&#9728;';
          } else {
            root.classList.remove('ig-dark-mode');
            if (darkBtn) darkBtn.textContent = '&#127769;';
          }
        }
        // Apply stored preference on load (localStorage wins for instant paint).
        try {
          var stored = window.localStorage.getItem('am_dark_mode');
          if (stored !== null) applyDark(stored === '1');
        } catch (e) {}
        if (darkBtn) {
          darkBtn.addEventListener('click', function () {
            var on = !root.classList.contains('ig-dark-mode');
            applyDark(on);
            try { window.localStorage.setItem('am_dark_mode', on ? '1' : '0'); } catch (e) {}
            socialRequest('am_dark_mode', { on: on ? 1 : 0 }).catch(function () {});
          });
        }
      }

      /* ---- Compact drag & drop photo areas ---- */
      root.querySelectorAll('.ig-dropzone').forEach(function (zone) {
        var input = zone.querySelector('input[type="file"]');
        if (!input) return;
        ['dragenter', 'dragover'].forEach(function (ev) {
          zone.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('is-dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
          zone.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.remove('is-dragover'); });
        });
        zone.addEventListener('drop', function (e) {
          var dt = e.dataTransfer;
          if (!dt || !dt.files || !dt.files[0]) return;
          try { input.files = dt.files; } catch (err) { return; }
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });

      /* ---- Stories: add, view, navigate, delete ---- */
      var storiesEl = root.querySelector('#ig-stories');
      var addModal  = root.querySelector('.ig-story-add-modal');
      var viewer    = root.querySelector('.ig-story-viewer');
      var storyForm = root.querySelector('.ig-story-form');
      var storyImg  = root.querySelector('#ig-story-image');
      var storyPrev = root.querySelector('.ig-story-preview');
      var storyMsg  = root.querySelector('.ig-story-msg');
      var groups = [];
      try { groups = JSON.parse( storiesEl ? storiesEl.getAttribute('data-stories') : '[]' ) || []; } catch (err) { groups = []; }
      var gi = 0, ii = 0, storyTimer = null;

      function showStoryMsg(text) { if (storyMsg) { storyMsg.textContent = text; storyMsg.hidden = false; } }
      root.querySelectorAll('.ig-modal-close').forEach(function (b) {
        b.addEventListener('click', function () { var m = b.closest('.ig-modal'); if (m) m.hidden = true; });
      });
      root.querySelectorAll('.ig-modal').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) m.hidden = true; });
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') root.querySelectorAll('.ig-modal').forEach(function (m) { m.hidden = true; });
      });
      var storyOpen = root.querySelector('#ig-story-open');
      if (storyOpen && addModal) storyOpen.addEventListener('click', function () { addModal.hidden = false; });
      if (storyImg && storyPrev && storyPrev.querySelector('img')) {
        storyImg.addEventListener('change', function () {
          var f = storyImg.files && storyImg.files[0];
          if (!f) { storyPrev.hidden = true; return; }
          storyPrev.querySelector('img').src = URL.createObjectURL(f);
          storyPrev.hidden = false;
        });
      }
      if (storyForm) storyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!storyImg || !storyImg.files || !storyImg.files[0]) { showStoryMsg('Choose a photo first.'); return; }
        if (storyMsg) storyMsg.hidden = true;
        var fd = new FormData(storyForm);
        fd.append('action', 'am_story_add'); fd.append('nonce', socialNonce);
        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (j.success) location.href = location.pathname + '?v=' + Date.now();
            else if (j.data && j.data.message) showStoryMsg(j.data.message);
          })
          .catch(function () { showStoryMsg('Could not share story. Please try again.'); });
      });

      function setViewerAvatar(el, g) {
        el.innerHTML = '';
        var a = g.avatar || '';
        if (/^https?:\/\//.test(a)) { var im = document.createElement('img'); im.src = a; im.alt = ''; el.appendChild(im); }
        else { el.textContent = a; }
      }
      function renderStory() {
        if (!viewer || !groups[gi] || !groups[gi].items[ii]) return;
        var g = groups[gi], item = g.items[ii];
        socialRequest('am_story_view', { id: item.id });
        if (insightsPanel) insightsPanel.hidden = true;
        if (insightsBtn) insightsBtn.hidden = !g.own;
        setViewerAvatar(viewer.querySelector('.ig-story-viewer-avatar'), g);
        viewer.querySelector('.ig-story-viewer-meta strong').textContent = g.name;
        viewer.querySelector('.ig-story-viewer-meta small').textContent = item.time + ' ago';
        var im = viewer.querySelector('.ig-story-viewer-img');
        im.src = item.image; im.alt = '';
        viewer.querySelector('.ig-story-viewer-caption').textContent = item.caption || '';
        var del = viewer.querySelector('.ig-story-viewer-delete');
        if (del) { del.hidden = !g.own; del.setAttribute('data-id', item.id); }
        var vcount = viewer.querySelector('.ig-story-views');
        if (vcount) vcount.textContent = item.views || 0;
        var bar = viewer.querySelector('.ig-story-progress');
        bar.innerHTML = g.items.map(function (it, k) {
          return '<span class="ig-story-seg' + (k < ii ? ' is-done' : '') + (k === ii ? ' is-active' : '') + '"><i></i></span>';
        }).join('');
        var active = bar.querySelector('.ig-story-seg.is-active i');
        if (active) { active.style.animation = 'none'; void active.offsetWidth; active.style.animation = ''; }
        clearTimeout(storyTimer);
        storyTimer = setTimeout(nextStory, 5000);
      }
      function nextStory() {
        if (!groups[gi]) return;
        if (ii < groups[gi].items.length - 1) { ii++; renderStory(); }
        else if (gi < groups.length - 1) { gi++; ii = 0; renderStory(); }
        else closeViewer();
      }
      function prevStory() {
        if (ii > 0) { ii--; renderStory(); }
        else if (gi > 0) { gi--; ii = groups[gi].items.length - 1; renderStory(); }
        else { ii = 0; renderStory(); }
      }
      function openViewer(index) { if (viewer && groups[index]) { gi = index; ii = 0; viewer.hidden = false; renderStory(); } }
      function closeViewer() { if (viewer) viewer.hidden = true; clearTimeout(storyTimer); }
      if (storiesEl) storiesEl.addEventListener('click', function (e) {
        var card = e.target.closest('.ig-story[data-story-index]');
        if (card) openViewer(parseInt(card.getAttribute('data-story-index'), 10) || 0);
      });
      var vClose = root.querySelector('.ig-story-viewer-close'); if (vClose) vClose.addEventListener('click', closeViewer);
      var vNext = root.querySelector('.ig-story-next'); if (vNext) vNext.addEventListener('click', nextStory);
      var vPrev = root.querySelector('.ig-story-prev'); if (vPrev) vPrev.addEventListener('click', prevStory);
      var insightsBtn = root.querySelector('.ig-story-viewer-insights');
      var insightsPanel = root.querySelector('.ig-story-insights-panel');
      if (insightsBtn && insightsPanel) insightsBtn.addEventListener('click', function () {
        var show = insightsPanel.hidden;
        if (!show) {
          insightsPanel.hidden = true;
          storyTimer = setTimeout(nextStory, 5000);
          return;
        }
        // Pause auto-advance while the member reads the viewer list.
        clearTimeout(storyTimer);
        var g = groups[gi], item = g && g.items ? g.items[ii] : null;
        if (!item) { storyTimer = setTimeout(nextStory, 5000); return; }
        socialRequest('am_story_viewers', { id: item.id }).then(function (j) {
          if (j.success) {
            var list = insightsPanel.querySelector('[data-story-viewer-list]');
            if (list) list.innerHTML = j.data.html;
            var cnt = insightsPanel.querySelector('.ig-story-insights-count');
            if (cnt) cnt.textContent = j.data.count + (j.data.count === 1 ? ' view' : ' views');
            insightsPanel.hidden = false;
          } else {
            storyTimer = setTimeout(nextStory, 5000);
          }
        }).catch(function () { storyTimer = setTimeout(nextStory, 5000); });
      });
      var vDel = root.querySelector('.ig-story-viewer-delete');
      if (vDel) vDel.addEventListener('click', function () {
        socialRequest('am_story_delete', { id: vDel.getAttribute('data-id') }).then(function (j) {
          if (j.success) location.href = location.pathname + '?v=' + Date.now();
        });
      });
      document.addEventListener('keydown', function (e) {
        if (!viewer || viewer.hidden) return;
        if (e.key === 'ArrowRight') nextStory();
        if (e.key === 'ArrowLeft') prevStory();
      });
    });
  });
})();
