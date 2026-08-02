/**
 * form-nav.js — Navigasi keyboard untuk form input cepat
 *
 * Fitur:
 *  - Auto-fokus ke field pertama saat halaman/tab dibuka
 *  - Tombol Enter memindahkan fokus ke field berikutnya (seperti Tab)
 *  - Enter pada field terakhir akan menyimpan (submit) form
 *  - Tab tetap berfungsi normal
 *
 * Cara pakai: tambahkan atribut  data-formnav  pada elemen <form>.
 */
(function () {
  function isVisible(el) {
    return !!(el.offsetParent !== null || el.getClientRects().length);
  }

  // Kumpulkan field yang bisa difokus (urut sesuai tampilan di layar)
  function focusableFields(form) {
    const nodes = form.querySelectorAll('input, select, textarea');
    return Array.prototype.filter.call(nodes, function (el) {
      if (el.type === 'hidden' || el.disabled || el.readOnly) return false;
      if (el.type === 'checkbox' || el.type === 'radio') return false;
      if (el.tabIndex === -1) return false;
      if (!isVisible(el)) return false;
      return true;
    });
  }

  function focusField(el) {
    if (!el) return;
    el.focus();
    // Pilih teks yang ada agar mudah ditimpa
    if (typeof el.select === 'function') {
      try { el.select(); } catch (e) { /* select tidak berlaku utk semua tipe */ }
    }
  }

  function initForm(form) {
    if (form.__formNavInit) return;
    form.__formNavInit = true;

    form.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      const el = e.target;
      // Biarkan textarea membuat baris baru
      if (el.tagName === 'TEXTAREA') return;
      // Jika sedang membuka dropdown datalist, biarkan Enter memilih dulu
      const fields = focusableFields(form);
      const idx = fields.indexOf(el);
      if (idx === -1) return;

      e.preventDefault();
      if (idx < fields.length - 1) {
        focusField(fields[idx + 1]);
      } else {
        // Field terakhir -> simpan form
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      }
    });
  }

  function autofocusFirst(form) {
    const fields = focusableFields(form);
    if (fields.length) focusField(fields[0]);
  }

  function firstVisibleForm(forms) {
    for (let i = 0; i < forms.length; i++) {
      if (isVisible(forms[i])) return forms[i];
    }
    return null;
  }

  document.addEventListener('DOMContentLoaded', function () {
    const forms = Array.prototype.slice.call(document.querySelectorAll('form[data-formnav]'));
    forms.forEach(initForm);

    const first = firstVisibleForm(forms);
    if (first) {
      // Beri sedikit jeda agar data dinamis (dropdown) sempat termuat
      setTimeout(function () { autofocusFirst(first); }, 200);
    }
  });

  // Saat berpindah tab (Bootstrap), fokuskan field pertama tab yang aktif
  document.addEventListener('shown.bs.tab', function (e) {
    const sel = e.target.getAttribute('data-bs-target') || e.target.getAttribute('href');
    if (!sel) return;
    const pane = document.querySelector(sel);
    if (!pane) return;
    const form = pane.matches('form[data-formnav]') ? pane : pane.querySelector('form[data-formnav]');
    if (form) setTimeout(function () { autofocusFirst(form); }, 100);
  });

  // Ekspos bila perlu dipanggil manual
  window.formNavFocus = autofocusFirst;
})();
