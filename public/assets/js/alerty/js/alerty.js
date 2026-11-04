/* Alerty 1.2.0 - standalone, dependency-free popup manager. MIT License. */
(function (global) {
  'use strict';
  const registry = new Map();
  let sequence = 0;
  let highest = 20000;
  const messages = new Map();
  const messageTypes = new Set(['message', 'info', 'success', 'error', 'warning']);
  const messagePositions = new Set(['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right']);
  const defaults = {
    modal: true, closable: true, closableByDimmer: false,
    closeOnEscape: true, movable: true, resizable: false, maximizable: true,
    width: 'min(560px, calc(100vw - 32px))', height: 'auto',
    transition: 'zoom', padding: true, autoReset: true,
    labels: { ok: 'Megerősítés', cancel: 'Mégse' },
    onshow: null, onclose: null, onclosing: null
  };
  const isNode = value => typeof Node !== 'undefined' && value instanceof Node;
  const setHTML = (target, content) => {
    target.replaceChildren();
    if (isNode(content)) target.appendChild(content);
    else target.innerHTML = content == null ? '' : String(content);
  };
  const uniqueId = () => `alerty-${Date.now().toString(36)}-${(++sequence).toString(36)}`;
  const DEFAULT_FOOTER = `
    <div class="container-fluid p-0">
      <div class="row">
        <div class="col-12 text-end">
          <button type="button" class="btn btn-default btn-sm btn-secondary" data-name="btnAlertyCancel">
            <i class="alerty-cancel-icon" aria-hidden="true"></i> Bezárás
          </button>
        </div>
      </div>
    </div>`;

  function ensureReady() {
    if (!document.body) throw new Error('Alerty: hívás csak a DOM betöltődése után lehetséges.');
  }
  function topmost() {
    return [...registry.values()].filter(p => p.isOpen()).sort((a, b) => b._z - a._z)[0] || null;
  }
  function restackPopups() {
    // Earlier dialogs remain above the newcomers on screen; newest is visually lower.
    [...registry.values()].filter(p => p.isOpen()).forEach((popup, index) => {
      popup.root.style.setProperty('--alerty-stack-offset', `${Math.min(index * 38, 152)}px`);
    });
  }
  function ensureMessageContainer(position) {
    const containerId = `alerty-messages-${position}`;
    let el = document.getElementById(containerId);
    if (!el) {
      el = document.createElement('div');
      el.id = containerId;
      el.className = 'alerty-messages';
      el.dataset.position = position;
      el.setAttribute('aria-label', 'Értesítések');
      document.body.appendChild(el);
    }
    return el;
  }
  function createMessage(content, type = 'message', seconds = 5, position = 'bottom-right') {
    ensureReady();
    type = String(type || 'message').toLowerCase();
    if (!messageTypes.has(type)) type = 'message';
    position = String(position || 'bottom-right').toLowerCase();
    if (!messagePositions.has(position)) position = 'bottom-right';
    const container = ensureMessageContainer(position);
    const duration = Number(seconds);
    const id = uniqueId();
    const node = document.createElement('div');
    node.className = `alerty-toast alerty-toast-${type}`;
    node.dataset.alertyId = id;
    node.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const body = document.createElement('span');
    body.className = 'alerty-toast-text';
    body.textContent = String(content ?? ''); // notification text is never interpreted as HTML
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'alerty-toast-close';
    button.setAttribute('aria-label', 'Értesítés bezárása');
    node.append(body, button);
    const item = {
      id, type, position, node, timer: null, isOpen: () => messages.has(id),
      close() {
        if (!messages.has(id)) return this;
        clearTimeout(this.timer);
        messages.delete(id);
        node.remove();
        if (!container.childElementCount) container.remove();
        return this;
      }
    };
    button.addEventListener('click', () => item.close());
    messages.set(id, item);
    container.appendChild(node); // newest notice goes below older notices at the same position
    if (Number.isFinite(duration) && duration > 0) {
      item.timer = setTimeout(() => item.close(), duration * 1000);
    }
    return id;
  }
  function dispatchSafe(fn, instance, event) {
    if (typeof fn !== 'function') return undefined;
    try { return fn.call(instance, event); }
    catch (error) { setTimeout(() => { throw error; }, 0); return false; }
  }
  class Popup {
    constructor(type, title, content, options = {}) {
      ensureReady();
      this.id = uniqueId();
      this.type = type;
      this.options = { ...defaults, ...options, labels: { ...defaults.labels, ...(options.labels || {}) } };
      this._handlers = { save: null, cancel: null, close: null, ok: null };
      this._closed = false;
      this._maximized = false;
      this._z = ++highest;
      this._priorFocus = document.activeElement;
      this._build();
      this.setHeader(title);
      this.setContent(content);
      this.setFooter(null);
      this._applyOptions();
      registry.set(this.id, this);
      restackPopups();
      this.root.classList.add('alerty-visible');
      this._syncScroll();
      this.dialog.focus({ preventScroll: true });
      dispatchSafe(this.options.onshow, this);
      this.root.dispatchEvent(new CustomEvent('alerty:show', { bubbles: true, detail: { id: this.id, instance: this } }));
    }
    _build() {
      const root = document.createElement('div');
      root.className = 'alerty-root';
      root.dataset.alertyId = this.id;
      root.dataset.alertyType = this.type;
      root.style.zIndex = this._z;
      root.innerHTML = `<div class="alerty-overlay"></div>
        <section class="alerty-dialog" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="${this.id}-title">
          <header class="alerty-header">
            <div class="alerty-title" id="${this.id}-title"></div>
            <div class="alerty-tools">
              <button type="button" class="alerty-icon-btn alerty-maximize" data-action="maximize" aria-label="Teljes méret" title="Teljes méret"><span aria-hidden="true">□</span></button>
              <button type="button" class="alerty-icon-btn alerty-close" data-action="close" aria-label="Bezárás" title="Bezárás"><span aria-hidden="true">×</span></button>
            </div>
          </header>
          <div class="alerty-content"></div>
          <footer class="alerty-footer"></footer>
        </section>`;
      this.root = root;
      this.dialog = root.querySelector('.alerty-dialog');
      this.header = root.querySelector('.alerty-header');
      this.content = root.querySelector('.alerty-content');
      this.footer = root.querySelector('.alerty-footer');
      this.overlay = root.querySelector('.alerty-overlay');
      this.titleElement = root.querySelector('.alerty-title');
      root.addEventListener('mousedown', () => this.bringToFront());
      root.addEventListener('click', event => this._handleClick(event));
      this.header.addEventListener('pointerdown', event => this._dragStart(event));
      document.body.appendChild(root);
    }
    _confirmFooter() {
      return `<div class="alerty-actions"><button type="button" class="btn btn-sm btn-primary" data-name="btnAlertySave"><i class="alerty-save-icon" aria-hidden="true"></i><span class="alerty-button-label"></span></button><button type="button" class="btn btn-sm btn-secondary" data-name="btnAlertyCancel"><i class="alerty-cancel-icon" aria-hidden="true"></i><span class="alerty-button-label"></span></button></div>`;
    }
    _handleClick(event) {
      if (event.target === this.overlay) {
        if (this.options.closableByDimmer) this.close();
        return;
      }
      const action = event.target.closest('[data-action]');
      if (action && this.root.contains(action)) {
        if (action.dataset.action === 'close') this.close();
        if (action.dataset.action === 'maximize') this.maximize();
        return;
      }
      const btn = event.target.closest('[data-name]');
      if (!btn || !this.footer.contains(btn)) return;
      if (btn.dataset.name === 'btnAlertySave') this.save(event);
      else if (btn.dataset.name === 'btnAlertyCancel') this.cancel(event);
      else if (btn.dataset.name === 'btnAlertyOk') {
        if (this._handlers.ok) dispatchSafe(this._handlers.ok, this, event);
        else this.close();
      }
    }
    _applyOptions() {
      this.root.classList.toggle('alerty-nonmodal', !this.options.modal);
      this.root.classList.toggle('alerty-no-padding', !this.options.padding);
      this.root.classList.toggle('alerty-resizable', !!this.options.resizable);
      this.root.dataset.transition = this.options.transition;
      this.root.querySelector('.alerty-close').hidden = !this.options.closable;
      this.root.querySelector('.alerty-maximize').hidden = !this.options.maximizable;
      this.dialog.style.width = typeof this.options.width === 'number' ? `${this.options.width}px` : this.options.width;
      this.dialog.style.height = typeof this.options.height === 'number' ? `${this.options.height}px` : this.options.height;
      if (this.type === 'confirm' && this._defaultFooter) {
        const ok = this.footer.querySelector('[data-name="btnAlertySave"] .alerty-button-label');
        const cancel = this.footer.querySelector('[data-name="btnAlertyCancel"] .alerty-button-label');
        if (ok) ok.textContent = this.options.labels.ok;
        if (cancel) cancel.textContent = this.options.labels.cancel;
      }
    }
    _dragStart(event) {
      if (!this.options.movable || this._maximized || event.button !== 0 || event.target.closest('button')) return;
      const rect = this.dialog.getBoundingClientRect();
      this.dialog.style.left = `${rect.left}px`;
      this.dialog.style.top = `${rect.top}px`;
      this.dialog.style.transform = 'none';
      this.dialog.style.margin = '0';
      this.dialog.classList.add('alerty-dragging');
      const startX = event.clientX, startY = event.clientY;
      const originalLeft = rect.left, originalTop = rect.top;
      const move = e => {
        const l = Math.max(0, Math.min(window.innerWidth - Math.min(rect.width, 80), originalLeft + e.clientX - startX));
        const t = Math.max(0, Math.min(window.innerHeight - 56, originalTop + e.clientY - startY));
        this.dialog.style.left = `${l}px`;
        this.dialog.style.top = `${t}px`;
      };
      const end = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', end);
        this.dialog.classList.remove('alerty-dragging');
      };
      window.addEventListener('pointermove', move);
      window.addEventListener('pointerup', end, { once: true });
    }
    _syncScroll() {
      document.body.classList.toggle('alerty-has-modal', [...registry.values()].some(p => p.isOpen() && p.options.modal));
    }
    getId() { return this.id; }
    getid() { return this.getId(); }
    isOpen() { return !this._closed; }
    show() { if (this.isOpen()) this.bringToFront(); return this; }
    bringToFront() { if (this.isOpen()) { this._z = ++highest; this.root.style.zIndex = this._z; } return this; }
    setHeader(html) { setHTML(this.titleElement, html); return this; }
    setContent(html) { setHTML(this.content, html); return this; }
    /** Resize to percentages of the viewport, accepting numbers or percentage strings. */
    resizeTo(width, height) {
      const percentage = value => {
        const text = String(value).trim();
        const number = /^(?:\d+(?:\.\d*)?|\.\d+)%?$/.test(text) ? Number(text.replace(/%$/, '')) : NaN;
        if (!Number.isFinite(number) || number <= 0 || number > 100) {
          throw new RangeError('Alerty: resizeTo méretei 0-nál nagyobb, legfeljebb 100 százalékos értékek lehetnek.');
        }
        return number;
      };
      const viewportWidth = percentage(width);
      const viewportHeight = percentage(height);
      this.root.classList.add('alerty-viewport-sized');
      return this.set({ width: `${viewportWidth}vw`, height: `${viewportHeight}vh` });
    }
    setButtons(preset) {
      const save = '<button type="button" class="btn btn-sm btn-success" data-name="btnAlertySave"><i class="alerty-save-icon" aria-hidden="true"></i> Mentés</button>';
      const confirm = '<button type="button" class="btn btn-sm btn-primary" data-name="btnAlertySave"><i class="alerty-save-icon" aria-hidden="true"></i> Megerősítés</button>';
      const cancel = '<button type="button" class="btn btn-sm btn-secondary" data-name="btnAlertyCancel"><i class="alerty-cancel-icon" aria-hidden="true"></i> Mégse</button>';
      const ok = '<button type="button" class="btn btn-sm btn-primary" data-name="btnAlertyOk">OK</button>';
      const presets = new Map([
        ['buttonsSaveCancel', save + cancel],
        ['buttonsConfirmCancel', confirm + cancel],
        ['buttonsOk', ok],
        ['buttonsCancel', cancel]
      ]);
      if (!presets.has(preset)) throw new RangeError(`Alerty: ismeretlen gombkészlet: ${preset}`);
      return this.setFooter(`<div class="alerty-actions">${presets.get(preset)}</div>`);
    }
    setFooter(html) {
      this._defaultFooter = html == null;
      setHTML(this.footer, html == null ? (this.type === 'confirm' ? this._confirmFooter() : DEFAULT_FOOTER) : html);
      if (this.type === 'confirm') this._applyOptions();
      return this;
    }
    /** Register a Save click handler. Save does not close the popup automatically. */
    onSave(fn) { this._handlers.save = fn; return this; }
    /** Register a Cancel click handler; custom handlers control closing themselves. */
    onCancel(fn) { this._handlers.cancel = fn; return this; }
    /** Called after any successful closing, regardless of its cause. */
    onClose(fn) { this._handlers.close = fn; return this; }
    /** Trigger the same action as the footer Save button. */
    save(event) {
      if (!this.isOpen()) return this;
      const handler = this._handlers.save || this._handlers.ok;
      if (handler) dispatchSafe(handler, this, event);
      else if (this.type === 'confirm') this.close();
      return this;
    }
    /** Trigger the same action as the footer Cancel button. */
    cancel(event) {
      if (!this.isOpen()) return this;
      if (this._handlers.cancel) dispatchSafe(this._handlers.cancel, this, event);
      else this.close();
      return this;
    }
    // Compatibility aliases for existing integrations.
    clickSaveBrn(fn) { return this.onSave(fn); }
    clickSaveBtn(fn) { return this.onSave(fn); }
    clickCancelBrn(fn) { return this.onCancel(fn); }
    clickCancelBtn(fn) { return this.onCancel(fn); }
    onOk(fn) { this._handlers.ok = fn; return this; }
    set(key, value) {
      if (key && typeof key === 'object') { Object.entries(key).forEach(([k, v]) => this.set(k, v)); return this; }
      if (key === 'title') return this.setHeader(value);
      if (key === 'message' || key === 'content') return this.setContent(value);
      if (key === 'footer') return this.setFooter(value);
      if (key === 'onok') return this.onOk(value);
      if (key === 'onsave') return this.onSave(value);
      if (key === 'onClose') return this.onClose(value);
      if (key === 'onSave') return this.onSave(value);
      if (key === 'onCancel') return this.onCancel(value);
      if (key === 'oncancel') return this.onCancel(value);
      if (key === 'onshow' || key === 'onclose' || key === 'onclosing') { this.options[key] = value; return this; }
      if (key === 'labels' && value && typeof value === 'object') this.options.labels = { ...this.options.labels, ...value };
      else this.options[key] = value;
      this._applyOptions();
      this._syncScroll();
      return this;
    }
    setting(key, value) { return arguments.length === 1 ? this.get(key) : this.set(key, value); }
    get(key) {
      if (key === 'title') return this.titleElement.innerHTML;
      if (key === 'message' || key === 'content') return this.content.innerHTML;
      if (key === 'footer') return this.footer.innerHTML;
      if (key === 'onok') return this._handlers.ok;
      if (key === 'oncancel' || key === 'onCancel') return this._handlers.cancel;
      if (key === 'onsave' || key === 'onSave') return this._handlers.save;
      if (key === 'onClose') return this._handlers.close;
      return this.options[key];
    }
    maximize() {
      if (!this.isOpen()) return this;
      this._maximized = !this._maximized;
      this.dialog.classList.toggle('alerty-maximized', this._maximized);
      this.root.querySelector('.alerty-maximize').setAttribute('aria-label', this._maximized ? 'Visszaállítás' : 'Teljes méret');
      return this;
    }
    close() {
      if (!this.isOpen()) return this;
      if (dispatchSafe(this.options.onclosing, this) === false) return this;
      this._closed = true;
      registry.delete(this.id);
      restackPopups();
      this.root.remove();
      this._syncScroll();
      dispatchSafe(this.options.onclose, this);
      dispatchSafe(this._handlers.close, this);
      if (this._priorFocus && typeof this._priorFocus.focus === 'function' && this._priorFocus.isConnected) this._priorFocus.focus({ preventScroll: true });
      const next = topmost();
      if (next) next.dialog.focus({ preventScroll: true });
      return this;
    }
    destroy() { return this.close(); }
    /** Quickly look up a DOM element belonging to this popup. */
    find(selector) { return this.root.querySelector(selector); }
  }
  const alerty = {
    version: '1.2.0',
    defaults,
    alert(title, content, options = {}) {
      if (arguments.length === 1) { content = title; title = 'Értesítés'; }
      return new Popup('alert', title, content, options);
    },
    popup(...args) { return this.alert(...args); },
    confirm(title, message, onok, oncancel, options = {}) {
      if (typeof message === 'function') { options = oncancel || {}; oncancel = onok; onok = message; message = title; title = 'Megerősítés'; }
      const p = new Popup('confirm', title, message, options);
      if (typeof onok === 'function') p.onOk(function (event) { const result = onok.call(this, event); if (result !== false) this.close(); return result; });
      if (typeof oncancel === 'function') p.onCancel(function (event) { const result = oncancel.call(this, event); if (result !== false) this.close(); return result; });
      return p;
    },
    message(content, type = 'message', seconds = 5, position = 'bottom-right') { return createMessage(content, type, seconds, position); },
    get(id) { const key = typeof id === 'object' && id ? id.id : id; return registry.get(key) || messages.get(key) || null; },
    close(id) { const p = this.get(id); if (p) p.close(); return this; },
    closeAll() { [...registry.values()].forEach(p => p.close()); return this; },
    closeAllMessages() { [...messages.values()].forEach(m => m.close()); return this; },
    getAll() { return [...registry.values()]; },
    set(key, value) {
      if (key && typeof key === 'object') Object.entries(key).forEach(([k, v]) => this.set(k, v));
      else if (key === 'labels' && value && typeof value === 'object') Object.assign(defaults.labels, value);
      else defaults[key] = value;
      return this;
    }
  };
  document.addEventListener('keydown', event => {
    const p = topmost();
    if (!p) return;
    if (event.key === 'Escape' && p.options.closeOnEscape && p.options.closable) {
      event.preventDefault(); p.close();
    }
    if (event.key === 'Tab' && p.options.modal) {
      const focusables = [...p.dialog.querySelectorAll('button:not([hidden]):not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href], [tabindex]:not([tabindex="-1"])')].filter(el => el.getClientRects().length);
      if (!focusables.length) { event.preventDefault(); p.dialog.focus(); return; }
      const first = focusables[0], last = focusables[focusables.length - 1];
      if (event.shiftKey && (document.activeElement === first || document.activeElement === p.dialog)) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  global.alerty = alerty;
})(window);
