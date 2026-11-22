/* Ababil Members — builder interactions (admin only). No globals leaked. */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    var list = document.getElementById('am-field-order');
    if (list) {
      var dragging = null;
      list.querySelectorAll('li[draggable="true"]').forEach(function (li) {
        li.addEventListener('dragstart', function () { dragging = li; li.classList.add('is-dragging'); });
        li.addEventListener('dragend', function () { li.classList.remove('is-dragging'); dragging = null; sync(); });
        li.addEventListener('dragover', function (e) {
          e.preventDefault();
          if (!dragging || dragging === li) return;
          var box = li.getBoundingClientRect();
          var after = (e.clientY - box.top) > (box.height / 2);
          list.insertBefore(dragging, after ? li.nextSibling : li);
        });
      });
      function sync() {
        list.querySelectorAll('li').forEach(function (li) {
          var input = li.querySelector('input[name="am_field_order[]"]');
          if (input && li.getAttribute('data-slug')) input.value = li.getAttribute('data-slug');
        });
      }
    }

    var select = document.getElementById('am-shortcode-select');
    var out = document.getElementById('am-shortcode-out');
    var btn = document.getElementById('am-shortcode-insert');
    if (select && out && btn) {
      select.addEventListener('change', function () { out.value = '[' + select.value + ']'; });
      btn.addEventListener('click', function () {
        out.focus(); out.select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(out.value).catch(function () {});
        } else {
          try { document.execCommand('copy'); } catch (e) {}
        }
        var old = btn.textContent; btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = old; }, 1500);
      });
    }
  });
})();
