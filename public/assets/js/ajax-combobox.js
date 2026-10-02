(function (window, $) {
    'use strict';

    /**
     * Újrahasználható AJAX autocomplete combobox.
     *
     * Használat:
     * new AjaxCombobox(rootElement, { endpoint: '/kereses', getFilters: () => ({ extra: 1 }) });
     * A végpont válasza: { success: true, data: { items: [
     *   { id, label, meta, selectable, badge, disabledReason }
     * ] } }.
     */
    let instanceCounter = 0;

    class AjaxCombobox {
        constructor(root, options) {
            this.options = Object.assign({
                endpoint: '',
                requestName: '',
                delay: 250,
                emptyText: 'Nincs találat.',
                loadingText: 'Keresés...',
                errorText: 'A keresés nem sikerült.',
                inactiveTitle: 'Nem választható',
                getFilters: null,
                onSelect: null,
                onClear: null
            }, options || {});

            this.$root = root instanceof $ ? root : $(root);
            if (!this.$root.length) {
                throw new Error('Az AjaxCombobox gyökéreleme nem található.');
            }
            if (!this.options.endpoint) {
                throw new Error('Az AjaxCombobox endpoint megadása kötelező.');
            }
            if (typeof window.Ajax !== 'function') {
                throw new Error('Az ajax.js betöltése szükséges az AjaxCombobox használatához.');
            }

            this.instanceId = ++instanceCounter;
            this.namespace = '.ajaxCombobox' + this.instanceId;
            this.requestName = this.options.requestName || ('ajax_combobox_' + this.instanceId);
            this.$search = this.$root.find('[data-ajax-combobox-search]').first();
            this.$value = this.$root.find('[data-ajax-combobox-value]').first();
            this.$results = this.$root.find('[data-ajax-combobox-results]').first();
            this.$clear = this.$root.find('[data-ajax-combobox-clear]').first();
            this.items = [];
            this.searchTimer = null;
            this.selectedLabel = this.trim(this.$search.val());
            this.formData = new FormData();

            if (!this.$search.length || !this.$value.length || !this.$results.length) {
                throw new Error('Az AjaxCombobox kötelező mezői hiányoznak.');
            }

            this.request = this.createRequest();
            this.bindEvents();
        }

        createRequest() {
            const self = this;

            return new window.Ajax({
                url: this.options.endpoint,
                name: this.requestName,
                type: 'POST',
                dataType: 'json',
                formData: this.formData,
                function_prepare: function () {
                    self.showMessage(self.options.loadingText);
                },
                function_success: function (result) {
                    if (!result || result.success !== true) {
                        self.showMessage(result?.data?.error || self.options.errorText);
                        return;
                    }

                    const items = Array.isArray(result.data?.items) ? result.data.items : [];
                    self.render(items);
                },
                function_error: function (xhr, status) {
                    if (status === 'abort') {
                        return;
                    }

                    self.showMessage(xhr.responseJSON?.data?.error || self.options.errorText);
                }
            });
        }

        bindEvents() {
            const self = this;

            this.$search
                .off(this.namespace)
                .on('focus' + this.namespace, function () {
                    self.queueSearch(0);
                })
                .on('input' + this.namespace, function () {
                    if (self.trim(self.$search.val()) !== self.selectedLabel && self.$value.val() !== '') {
                        self.$value.val('').trigger('change');
                    }
                    self.queueSearch(self.options.delay);
                })
                .on('keydown' + this.namespace, function (event) {
                    if (event.key === 'Escape') {
                        self.close();
                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        const $first = self.$results.find('[data-ajax-combobox-option]').first();
                        if ($first.length) {
                            event.preventDefault();
                            $first.trigger('focus');
                        }
                        return;
                    }

                    if (event.key === 'Enter') {
                        const $first = self.$results.find('[data-ajax-combobox-option]').first();
                        if ($first.length) {
                            event.preventDefault();
                            self.activateOption($first);
                        }
                    }
                });

            this.$results
                .off(this.namespace)
                .on('mousedown' + this.namespace, '[data-ajax-combobox-option]', function (event) {
                    event.preventDefault();
                    self.activateOption($(this));
                })
                .on('keydown' + this.namespace, '[data-ajax-combobox-option]', function (event) {
                    const $options = self.$results.find('[data-ajax-combobox-option]');
                    const currentIndex = $options.index(this);

                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        self.activateOption($(this));
                    } else if (event.key === 'ArrowDown' && currentIndex < $options.length - 1) {
                        event.preventDefault();
                        $options.eq(currentIndex + 1).trigger('focus');
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        if (currentIndex > 0) {
                            $options.eq(currentIndex - 1).trigger('focus');
                        } else {
                            self.$search.trigger('focus');
                        }
                    } else if (event.key === 'Escape') {
                        self.close();
                        self.$search.trigger('focus');
                    }
                });

            this.$clear
                .off(this.namespace)
                .on('click' + this.namespace, function () {
                    self.clear();
                    self.$search.trigger('focus');
                });

            $(document)
                .off('mousedown' + this.namespace)
                .on('mousedown' + this.namespace, function (event) {
                    if (!$(event.target).closest(self.$root).length) {
                        self.close();
                    }
                });
        }

        search() {
            window.clearTimeout(this.searchTimer);

            if (this.request.ajax && this.request.ajax.readyState !== 4) {
                this.request.ajax.abort();
            }

            const filters = typeof this.options.getFilters === 'function'
                ? Object.assign({}, this.options.getFilters() || {})
                : {};
            filters.search = this.trim(this.$search.val());
            this.formData.set('filters', JSON.stringify(filters));
            this.request.send();
        }

        queueSearch(delay) {
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => this.search(), Number(delay) || 0);
        }

        render(items) {
            this.items = items;
            this.$results.empty();

            if (!items.length) {
                this.showMessage(this.options.emptyText);
                return;
            }

            items.forEach((item, index) => {
                const selectable = item.selectable !== false;
                const $option = $('<button>', {
                    type: 'button',
                    class: 'list-group-item list-group-item-action ajax-combobox__option',
                    role: 'option',
                    tabindex: '0',
                    'data-ajax-combobox-option': String(index),
                    'aria-disabled': selectable ? 'false' : 'true',
                    disabled: !selectable
                }).toggleClass('ajax-combobox__option--inactive', !selectable);

                const $title = $('<span>', { class: 'ajax-combobox__title' })
                    .append($('<span>', { text: String(item.label ?? '') }));

                if (item.badge) {
                    $title.append($('<span>', {
                        class: 'badge rounded-pill ajax-combobox__badge',
                        text: String(item.badge)
                    }));
                }

                $option.append($title);
                if (item.meta) {
                    $option.append($('<span>', {
                        class: 'ajax-combobox__meta',
                        text: String(item.meta)
                    }));
                }

                this.$results.append($option);
            });

            this.open();
        }

        activateOption($option) {
            const index = Number($option.attr('data-ajax-combobox-option'));
            const item = this.items[index];
            if (!item) {
                return;
            }

            if (item.selectable === false) {
                const message = item.disabledReason || 'Ez az elem nem választható ki.';
                if (window.alertify?.alert) {
                    window.alertify.alert(this.options.inactiveTitle, message);
                }
                return;
            }

            this.setValue(item.id, item.label, item);
        }

        setValue(id, label, item) {
            window.clearTimeout(this.searchTimer);
            if (this.request.ajax && this.request.ajax.readyState !== 4) {
                this.request.ajax.abort();
            }

            this.selectedLabel = String(label ?? '');
            this.$value.val(String(id ?? '')).trigger('change');
            this.$search.val(this.selectedLabel);
            this.close();

            if (item && typeof this.options.onSelect === 'function') {
                this.options.onSelect(item || { id: id, label: label }, this);
            }
        }

        clear() {
            this.setValue('', '', null);
            if (typeof this.options.onClear === 'function') {
                this.options.onClear(this);
            }
        }

        showMessage(message) {
            this.items = [];
            this.$results.empty().append($('<div>', {
                class: 'ajax-combobox__message',
                text: String(message || '')
            }));
            this.open();
        }

        open() {
            this.$results.removeClass('d-none');
            this.$search.attr('aria-expanded', 'true');
        }

        close() {
            this.$results.addClass('d-none');
            this.$search.attr('aria-expanded', 'false');
        }

        destroy() {
            window.clearTimeout(this.searchTimer);
            if (this.request.ajax && this.request.ajax.readyState !== 4) {
                this.request.ajax.abort();
            }
            this.$search.off(this.namespace);
            this.$clear.off(this.namespace);
            this.$results.off(this.namespace);
            $(document).off(this.namespace);
            this.close();
        }

        trim(value) {
            return String(value ?? '').trim();
        }
    }

    window.AjaxCombobox = AjaxCombobox;
})(window, window.jQuery);
