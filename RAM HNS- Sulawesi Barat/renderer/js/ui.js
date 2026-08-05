/* ============================================================================
 * ui.js — pengganti bootstrap.bundle.min.js
 *
 * Menyediakan Modal, Tab, dan Collapse dengan API yang sama persis seperti
 * Bootstrap 5, sehingga kode aplikasi yang sudah ada tidak perlu diubah:
 *
 *   new bootstrap.Modal(el).show()
 *   bootstrap.Modal.getInstance(el).hide()
 *   <button data-bs-toggle="modal"  data-bs-target="#id">
 *   <button data-bs-dismiss="modal">
 *   <button data-bs-toggle="tab"    data-bs-target="#pane">
 *   <a      data-bs-toggle="collapse" href="#id">
 *
 * Event yang dipancarkan (dipakai form-nav.js): shown.bs.tab, shown.bs.modal,
 * hidden.bs.modal.
 *
 * Styling-nya ada di styles/tailwind.css (bagian MODAL / TAB).
 * ========================================================================== */
(function (window, document) {
  'use strict';

  var INSTANCE_KEY = '__uiModal';

  function emit(el, type, detail) {
    el.dispatchEvent(new CustomEvent(type, { bubbles: true, detail: detail || {} }));
  }

  function resolveTarget(trigger) {
    var sel = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
    if (!sel || sel === '#') return null;
    try {
      return document.querySelector(sel);
    } catch (e) {
      return null;
    }
  }

  /* ==========================================================================
   * MODAL
   * ========================================================================= */
  function Modal(element, options) {
    if (typeof element === 'string') element = document.querySelector(element);
    if (!element) throw new Error('Modal: elemen tidak ditemukan');

    // Bootstrap mengembalikan instance yang sama untuk elemen yang sama
    if (element[INSTANCE_KEY]) {
      if (options) element[INSTANCE_KEY]._options = Object.assign(element[INSTANCE_KEY]._options, options);
      return element[INSTANCE_KEY];
    }

    this._element = element;
    this._options = Object.assign({ backdrop: true, keyboard: true, focus: true }, options || {});
    this._isShown = false;
    element[INSTANCE_KEY] = this;

    var self = this;

    // Klik pada area gelap (di luar .modal-dialog) menutup modal
    this._onClick = function (e) {
      if (e.target !== self._element) return;
      if (self._options.backdrop === 'static' || self._options.backdrop === false) return;
      self.hide();
    };
    element.addEventListener('click', this._onClick);
  }

  Modal.prototype.show = function () {
    if (this._isShown) return;
    var el = this._element;
    var self = this;

    this._isShown = true;
    document.body.classList.add('modal-open');
    el.classList.add('showing');
    el.removeAttribute('aria-hidden');
    el.setAttribute('aria-modal', 'true');

    // Paksa reflow supaya transisi opacity berjalan
    void el.offsetWidth;
    el.classList.add('show');

    if (this._options.focus !== false) {
      var focusable = el.querySelector(
        'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])'
      );
      if (focusable) {
        setTimeout(function () {
          try { focusable.focus(); } catch (e) {}
        }, 60);
      }
    }

    setTimeout(function () { emit(self._element, 'shown.bs.modal'); }, 150);
  };

  Modal.prototype.hide = function () {
    if (!this._isShown) return;
    var el = this._element;
    var self = this;

    this._isShown = false;
    el.classList.remove('show');

    setTimeout(function () {
      el.classList.remove('showing');
      el.setAttribute('aria-hidden', 'true');
      el.removeAttribute('aria-modal');
      // Lepas kunci scroll hanya bila tidak ada modal lain yang terbuka
      if (!document.querySelector('.modal.show')) {
        document.body.classList.remove('modal-open');
      }
      emit(self._element, 'hidden.bs.modal');
    }, 150);
  };

  Modal.prototype.toggle = function () {
    this._isShown ? this.hide() : this.show();
  };

  Modal.prototype.dispose = function () {
    this._element.removeEventListener('click', this._onClick);
    delete this._element[INSTANCE_KEY];
  };

  Modal.getInstance = function (element) {
    if (typeof element === 'string') element = document.querySelector(element);
    return element ? element[INSTANCE_KEY] || null : null;
  };

  Modal.getOrCreateInstance = function (element, options) {
    return Modal.getInstance(element) || new Modal(element, options);
  };

  /* ==========================================================================
   * TAB
   * ========================================================================= */
  function Tab(element) {
    if (typeof element === 'string') element = document.querySelector(element);
    this._element = element;
  }

  Tab.prototype.show = function () {
    var trigger = this._element;
    if (!trigger || trigger.classList.contains('active')) return;

    var pane = resolveTarget(trigger);
    if (!pane) return;

    // Nonaktifkan tombol tab sekelompok
    var navRoot = trigger.closest('.nav-tabs, .nav, [role="tablist"]') || document;
    navRoot.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (t) {
      t.classList.remove('active');
      t.setAttribute('aria-selected', 'false');
    });

    // Sembunyikan panel-panel sekelompok
    var paneRoot = pane.parentElement || document;
    paneRoot.querySelectorAll(':scope > .tab-pane').forEach(function (p) {
      p.classList.remove('active', 'show');
    });

    trigger.classList.add('active');
    trigger.setAttribute('aria-selected', 'true');
    pane.classList.add('active', 'show');

    emit(trigger, 'shown.bs.tab');
  };

  Tab.getInstance = function (element) {
    if (typeof element === 'string') element = document.querySelector(element);
    return element ? new Tab(element) : null;
  };
  Tab.getOrCreateInstance = function (element) {
    return new Tab(element);
  };

  /* ==========================================================================
   * COLLAPSE
   * ========================================================================= */
  function Collapse(element) {
    if (typeof element === 'string') element = document.querySelector(element);
    this._element = element;
  }

  Collapse.prototype.show = function () {
    this._element.classList.add('show');
    emit(this._element, 'shown.bs.collapse');
  };
  Collapse.prototype.hide = function () {
    this._element.classList.remove('show');
    emit(this._element, 'hidden.bs.collapse');
  };
  Collapse.prototype.toggle = function () {
    this._element.classList.contains('show') ? this.hide() : this.show();
  };
  Collapse.getInstance = function (el) {
    if (typeof el === 'string') el = document.querySelector(el);
    return el ? new Collapse(el) : null;
  };
  Collapse.getOrCreateInstance = function (el) {
    return new Collapse(el);
  };

  /* ==========================================================================
   * PENGAIT OTOMATIS (delegasi, jadi tetap jalan untuk HTML yang dibuat JS)
   * ========================================================================= */
  document.addEventListener('click', function (e) {
    // -- buka modal
    var openTrigger = e.target.closest('[data-bs-toggle="modal"]');
    if (openTrigger) {
      var modalEl = resolveTarget(openTrigger);
      if (modalEl) {
        e.preventDefault();
        Modal.getOrCreateInstance(modalEl).show();
        return;
      }
    }

    // -- tutup modal
    var dismissTrigger = e.target.closest('[data-bs-dismiss="modal"]');
    if (dismissTrigger) {
      var owner = dismissTrigger.closest('.modal');
      if (owner) {
        e.preventDefault();
        Modal.getOrCreateInstance(owner).hide();
        return;
      }
    }

    // -- pindah tab
    var tabTrigger = e.target.closest('[data-bs-toggle="tab"]');
    if (tabTrigger) {
      e.preventDefault();
      new Tab(tabTrigger).show();
      return;
    }

    // -- buka/tutup collapse
    var collapseTrigger = e.target.closest('[data-bs-toggle="collapse"]');
    if (collapseTrigger) {
      var box = resolveTarget(collapseTrigger);
      if (box) {
        e.preventDefault();
        new Collapse(box).toggle();
        collapseTrigger.setAttribute('aria-expanded', box.classList.contains('show') ? 'true' : 'false');
      }
    }
  });

  // Esc menutup modal teratas
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var open = Array.prototype.slice.call(document.querySelectorAll('.modal.show')).pop();
    if (!open) return;
    var inst = Modal.getInstance(open);
    if (inst && inst._options.keyboard !== false) inst.hide();
  });

  window.bootstrap = window.bootstrap || {};
  window.bootstrap.Modal = Modal;
  window.bootstrap.Tab = Tab;
  window.bootstrap.Collapse = Collapse;
})(window, document);
