(function ($) {
    'use strict';

    /**
     * Format a price value using WooCommerce currency settings
     * passed via wp_localize_script.
     */
    function formatPrice(amount) {
        const cfg = window.productbay_frontend || {};
        const decimals = parseInt(cfg.currency_decimals, 10) || 2;
        const decSep = cfg.currency_decimal_sep || '.';
        const thousandSep = cfg.currency_thousand_sep || ',';
        const symbol = cfg.currency_symbol || '$';
        const position = cfg.currency_position || 'left';

        // Format the number
        let number = parseFloat(amount).toFixed(decimals);
        const parts = number.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
        number = parts.join(decSep);

        switch (position) {
            case 'left':
                return symbol + number;
            case 'right':
                return number + symbol;
            case 'left_space':
                return symbol + '\u00a0' + number;
            case 'right_space':
                return number + '\u00a0' + symbol;
            default:
                return symbol + number;
        }
    }

    class ProductBayTable {
        constructor(wrapper) {
            this.$wrapper = $(wrapper);
            this.tableId = this.$wrapper.attr('id');

            // Elements
            this.$table = this.$wrapper.find('.productbay-table');
            this.$tbody = this.$table.find('tbody');
            this.$searchInput = this.$wrapper.find('.productbay-search input');
            this.$searchClear = this.$wrapper.find('.productbay-search-clear');
            this.$selectAll = this.$wrapper.find('.productbay-select-all');
            this.$bulkBtn = this.$wrapper.find('.productbay-btn-bulk');
            if (this.$bulkBtn.length) {
                this.originalBulkBtnHtml = this.$bulkBtn.html();
            }

            // State
            this.searchTimeout = null;
            // Map: productId → { quantity, price, variationId, attributes }
            this.selectedProducts = new Map();
            // Map: cartKey → { quantity, variationId, attributes, productId }
            this.cartQuantities = new Map();
            this.loadSelectionsFromStorage();
            this.syncWithWCCart(); // Sync initial cart state

            this.init();
        }

        init() {
            this.features = JSON.parse(this.$wrapper.attr('data-features') || '{}');
            this.bindEvents();
            this.initTaxonomyFilters();
            this.initFiltersClear();
            this.initLightbox();
            this.restoreSelections();
        }

        /**
         * Build a unique cart key from product ID and attributes.
         * Uses attribute values (sorted) to ensure each unique combination
         * gets its own entry, even when WooCommerce maps multiple combos
         * to the same variation_id ("any" attribute case).
         */
        buildCartKey(productId, variationId, attributes) {
            if (!variationId) return String(productId);
            const attrValues = Object.entries(attributes || {})
                .filter(([, v]) => v)
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([, v]) => v)
                .join('|');
            return `${productId}:${attrValues || variationId}`;
        }

        bindEvents() {
            // Search
            this.$searchInput.on('input', this.handleSearch.bind(this));
            this.$searchClear.on('click', this.clearSearch.bind(this));

            // Selection (scoped to this wrapper to prevent cross-table interference in tab blocks)
            this.$selectAll.on('change', this.toggleSelectAll.bind(this));
            this.$wrapper.on('change', '.productbay-select-product', this.handleRowSelect.bind(this));
            this.$wrapper.on('click', '.productbay-btn-clear-all', this.handleClearAll.bind(this));

            // Quantity changes
            this.$wrapper.on('input', '.productbay-qty', this.handleQuantityChange.bind(this));
            this.$wrapper.on('blur', '.productbay-qty', this.handleQuantityBlur.bind(this));

            // Variation selection
            this.$wrapper.on('change', '.productbay-variation-select', this.handleVariationChange.bind(this));

            // Quantity +/- buttons
            this.$wrapper.on('click', '.productbay-qty-minus', this.handleQtyMinus.bind(this));
            this.$wrapper.on('click', '.productbay-qty-plus', this.handleQtyPlus.bind(this));

            // Single add-to-cart buttons
            this.$wrapper.on('click', '.productbay-btn-addtocart', this.handleSingleAddToCart.bind(this));

            // Bulk Action
            this.$bulkBtn.on('click', this.handleBulkAddToCart.bind(this));

            // Pagination (delegated, since pagination HTML gets replaced)
            this.$wrapper.on('click', '.productbay-pagination a', this.handlePagination.bind(this));

            // Sync with actual WooCommerce Cart on AJAX refreshes
            $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', this.syncWithWCCart.bind(this));

            // Selected Items Popup
            this.$wrapper.on('click', '.productbay-btn-panel', this.toggleSelectedItemsPopup.bind(this));
            this.$wrapper.on('click', '.productbay-popup-close', this.closeSelectedItemsPopup.bind(this));
            this.$wrapper.on('click', '.productbay-popup-remove', this.handlePopupRemove.bind(this));
            this.$wrapper.on('click', '.productbay-popup-add-all', this.handlePopupAddAll.bind(this));

            // Custom Filter Trigger (for Pro Price Filter)
            this.$wrapper.on('productbay_filter_trigger', (e, data) => {
                const state = this.gatherFilterState();
                this.fetchProducts({ ...state, ...data, paged: 1, _context: 'filter' });
            });

            // Next Page Trigger (for Pro Lazy Loading)
            this.$wrapper.on('productbay_next_page', (e) => {
                const $pagination = this.$wrapper.find('.productbay-pagination');
                const current = parseInt($pagination.data('current'), 10) || 1;
                const total = parseInt($pagination.data('total'), 10) || 1;

                if (current < total) {
                    const state = this.gatherFilterState();
                    this.fetchProducts({ ...state, paged: current + 1, _append: true, _context: 'pagination' });
                }
            });

            // Pro Plugin Modal Persistence
            $(document.body).on('productbay_pro_modal_loaded', this.restoreModalSelections.bind(this));
        }

        /**
         * Parses the injected JSON cart data from the AJAX fragments.
         * Syncs this.cartQuantities with actual WooCommerce cart.
         */
        syncWithWCCart() {
            const $cartData = $('.productbay-cart-data').last();
            if ($cartData.length) {
                try {
                    const data = $cartData.data('cart');
                    if (Array.isArray(data)) {
                        this.cartQuantities.clear();
                        data.forEach(([k, v]) => this.cartQuantities.set(k, v));
                        this.restoreCartBadges();
                    }
                } catch (e) {
                    console.error('ProductBay: Failed to sync with WooCommerce cart fragments.', e);
                }
            }
        }

        /**
         * Restore previously checked items and quantities inside the newly injected HTML popup.
         */
        restoreModalSelections(e, modalContent) {
            const $modal = $(modalContent);

            $modal.find('.productbay-select-product').each((i, checkbox) => {
                const $checkbox = $(checkbox);
                const $row = $checkbox.closest('tr');
                const rawId = $checkbox.val();

                const storageKey = this.getStorageKey($row, rawId);

                if (this.selectedProducts.has(storageKey)) {
                    const savedItem = this.selectedProducts.get(storageKey);

                    // Restore checkbox state
                    $checkbox.prop('checked', true);

                    // Restore quantity input value
                    const $qtyInput = $row.find('.productbay-qty');
                    if ($qtyInput.length) {
                        $qtyInput.val(savedItem.quantity);

                        // Disable/enable arrows according to the restored value
                        const min = parseInt($qtyInput.attr('min'), 10) || 1;
                        const max = parseInt($qtyInput.attr('max'), 10) || Infinity;
                        this.updateQtyButtons($qtyInput, savedItem.quantity, min, max);
                    }
                }
            });
        }

        // ── Search ───────────────────────────────────────────────────────

        handleSearch(e) {
            const query = $(e.target).val();

            if (query.length > 0) {
                this.$wrapper.find('.productbay-search').addClass('has-value');
            } else {
                this.$wrapper.find('.productbay-search').removeClass('has-value');
            }

            if (this.searchTimeout) clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                const state = this.gatherFilterState();
                state.s = query;
                this.fetchProducts({ ...state, paged: 1, _context: 'search' });
            }, 400);
        }

        clearSearch() {
            this.$searchInput.val('').trigger('input');
        }



        initTaxonomyFilters() {
            const $filters = this.$wrapper.find('.productbay-taxonomy-filters');
            if (!$filters.length && !this.$wrapper.find('.productbay-price-filter').length) return;

            // Single-select dropdowns (e.g. product_type)
            $filters.on('change', '.productbay-filter-select', this.handleTaxonomyFilter.bind(this));

            // Custom multi-select dropdown toggle
            this.$wrapper.on('click', '.productbay-multiselect-trigger', function (e) {
                e.stopPropagation();
                const $dropdown = $(this).siblings('.productbay-multiselect-dropdown');
                const $parent = $(this).closest('.productbay-multiselect');

                // Close any other open dropdowns first
                $('.productbay-multiselect.productbay-multiselect-open').not($parent).removeClass('productbay-multiselect-open');

                $parent.toggleClass('productbay-multiselect-open');
            });

            // Checkbox change inside multi-select
            const self = this;
            this.$wrapper.on('change', '.productbay-multiselect-option input[type="checkbox"]', function () {
                const $multi = $(this).closest('.productbay-multiselect');
                const checked = $multi.find('input[type="checkbox"]:checked');
                const count = checked.length;
                const text = count > 0
                    ? count + ' selected'
                    : productbay_frontend.all_categories_text || 'All Categories';
                $multi.find('.productbay-multiselect-text').text(text);

                self.handleTaxonomyFilter();
            });

            // Close dropdown on outside click
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.productbay-multiselect').length) {
                    $('.productbay-multiselect.productbay-multiselect-open').removeClass('productbay-multiselect-open');
                }
            });
        }

        initFiltersClear() {
            this.$wrapper.on('click', '.productbay-filters-clear', this.handleFiltersClear.bind(this));
        }

        // ── Centralized Filter State ────────────────────────────────────

        /**
         * Collect the current state of ALL active filters into a single object.
         * Every AJAX call should use this to avoid losing active filters.
         */
        gatherFilterState() {
            const state = {
                s: this.$searchInput.val() || '',
                price_min: null,
                price_max: null,
                'product_cat[]': [],
                product_type: '',
                paged: 1,
            };

            // Categories (custom checkbox dropdown)
            const $multiselect = this.$wrapper.find('.productbay-multiselect[data-filter="product_cat"]');
            if ($multiselect.length) {
                const selected = [];
                $multiselect.find('input[type="checkbox"]:checked').each(function () {
                    selected.push($(this).val());
                });
                state['product_cat[]'] = selected;
            }

            // Product Type
            const $typeSelect = this.$wrapper.find('.productbay-filter-select[data-filter="product_type"]');
            if ($typeSelect.length) {
                state.product_type = $typeSelect.val() || '';
            }

            // Price Filter (Pro)
            const $priceFilter = this.$wrapper.find('.productbay-price-filter');
            if ($priceFilter.length) {
                const $minRange = $priceFilter.find('.productbay-price-range-min');
                const $maxRange = $priceFilter.find('.productbay-price-range-max');
                const $minInput = $priceFilter.find('.productbay-price-input-min');
                const $maxInput = $priceFilter.find('.productbay-price-input-max');

                state.price_min = $minRange.length ? $minRange.val() : ($minInput.length ? $minInput.val() : null);
                state.price_max = $maxRange.length ? $maxRange.val() : ($maxInput.length ? $maxInput.val() : null);
            }

            return state;
        }

        // ── Taxonomy Filter Handler ─────────────────────────────────────

        handleTaxonomyFilter() {
            const state = this.gatherFilterState();
            this.fetchProducts({ ...state, paged: 1, _context: 'filter' });
        }

        // ── Clear All Filters ───────────────────────────────────────────

        handleFiltersClear() {
            // Reset search
            this.$searchInput.val('');
            this.$searchClear.hide();

            // Reset single-select taxonomy dropdowns
            this.$wrapper.find('.productbay-filter-select').val('');

            // Reset custom multi-select checkboxes
            this.$wrapper.find('.productbay-multiselect input[type="checkbox"]').prop('checked', false);
            this.$wrapper.find('.productbay-multiselect-text').text(
                productbay_frontend.all_categories_text || 'All Categories'
            );
            this.$wrapper.find('.productbay-multiselect-open').removeClass('productbay-multiselect-open');

            // Fetch with empty state
            this.fetchProducts({ s: '', paged: 1, _context: 'filter' });
        }

        // ── Lightbox ────────────────────────────────────────────────────

        initLightbox() {
            const $dialog = this.$wrapper.find('.productbay-lightbox');
            if (!$dialog.length) return;

            this.$lightbox = $dialog;

            // Open Lightbox
            this.$wrapper.on('click', '.productbay-lightbox-trigger', (e) => {
                e.preventDefault();
                const src = $(e.currentTarget).data('full-url');
                if (src) {
                    $dialog.find('.productbay-lightbox-img').attr('src', src);
                    $dialog[0].showModal();
                }
            });

            // Helper to close lightbox safely
            const closeLightbox = () => {
                if ($dialog[0].open) {
                    $dialog.removeClass('productbay-lightbox-is-fullscreen');
                    $dialog.find('.productbay-icon-maximize').show();
                    $dialog.find('.productbay-icon-minimize').hide();

                    $dialog[0].close();
                    $dialog.find('.productbay-lightbox-img').attr('src', '');
                }
            };

            // Close Lightbox (via button or explicit backdrop)
            this.$wrapper.on('click', '.productbay-lightbox-close, .productbay-lightbox-backdrop', () => {
                closeLightbox();
            });

            // Close Lightbox (via native HTML5 dialog backdrop click)
            $dialog.on('click', (e) => {
                if (e.target === $dialog[0]) {
                    // Don't close if currently in fullscreen
                    if (!$dialog.hasClass('productbay-lightbox-is-fullscreen')) {
                        closeLightbox();
                    }
                }
            });

            // Toggle Fullscreen
            this.$wrapper.on('click', '.productbay-lightbox-fullscreen', () => {
                const isFullscreen = $dialog.hasClass('productbay-lightbox-is-fullscreen');
                if (isFullscreen) {
                    $dialog.removeClass('productbay-lightbox-is-fullscreen');
                    $dialog.find('.productbay-icon-maximize').show();
                    $dialog.find('.productbay-icon-minimize').hide();
                } else {
                    $dialog.addClass('productbay-lightbox-is-fullscreen');
                    $dialog.find('.productbay-icon-maximize').hide();
                    $dialog.find('.productbay-icon-minimize').show();
                }
            });

            // Make escape key work naturally
            $dialog.on('cancel', (e) => {
                e.preventDefault();
                closeLightbox();
            });
        }



        // ── Pagination ──────────────────────────────────────────────────

        handlePagination(e) {
            e.preventDefault();
            const href = $(e.currentTarget).attr('href');

            // 1. Try to get page number from query string (e.g. ?paged=2)
            const url = new URL(href, window.location.origin);
            let paged = url.searchParams.get('paged');

            // 2. If not found, try to extract from path (e.g. /page/2/ or /page/2)
            if (!paged) {
                const match = href.match(/\/page\/(\d+)\/?/);
                if (match) {
                    paged = match[1];
                }
            }

            paged = parseInt(paged, 10) || 1;
            const state = this.gatherFilterState();
            this.fetchProducts({ ...state, paged: paged, _context: 'pagination' });
        }

        // ── AJAX Fetch ──────────────────────────────────────────────────

        fetchProducts(args) {
            const context = args._context || 'table';
            const isAppend = !!args._append;
            delete args._context;
            delete args._append;

            if (context === 'search') {
                this.$wrapper.find('.productbay-search').addClass('loading');
            } else if (!isAppend) {
                this.$wrapper.addClass('productbay-loading');
            } else {
                this.$wrapper.find('.productbay-load-more-btn').addClass('loading').prop('disabled', true);
            }

            const data = {
                action: 'productbay_filter',
                nonce: productbay_frontend.nonce,
                table_id: this.$wrapper.data('table-id'),
                page_url: window.location.origin + window.location.pathname,
                ...args
            };

            $.ajax({
                url: productbay_frontend.ajaxurl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        if (isAppend) {
                            this.$tbody.append(response.data.html);
                        } else {
                            this.$tbody.html(response.data.html);
                        }

                        if (response.data.pagination) {
                            this.$wrapper.find('.productbay-pagination').replaceWith(response.data.pagination);
                        } else if (isAppend) {
                            // If appending and no new pagination, it means we reached the end
                            this.$wrapper.find('.productbay-pagination').remove();
                        }

                        // Restore selections for products visible on the new page
                        this.restoreSelections();

                        // Trigger event for Pro plugin or other extensions
                        this.$wrapper.trigger('productbay_after_fetch', [response, isAppend]);
                    }
                },
                complete: () => {
                    this.$wrapper.removeClass('productbay-loading');
                    this.$wrapper.find('.productbay-search').removeClass('loading');
                    this.$wrapper.find('.productbay-load-more-btn').removeClass('loading').prop('disabled', false);
                }
            });
        }

        loadSelectionsFromStorage() {
            try {
                const key = 'productbay_selections_' + this.$wrapper.data('table-id');
                const stored = sessionStorage.getItem(key);
                if (stored) {
                    const entries = JSON.parse(stored);
                    entries.forEach(([k, v]) => this.selectedProducts.set(k, v));
                }
            } catch (e) { /* silent */ }
        }

        saveSelectionsToStorage() {
            try {
                const key = 'productbay_selections_' + this.$wrapper.data('table-id');
                const entries = Array.from(this.selectedProducts.entries());
                sessionStorage.setItem(key, JSON.stringify(entries));
            } catch (e) { /* silent */ }
        }



        restoreSelections() {
            this.$tbody.find('.productbay-select-product').each((_, el) => {
                const $cb = $(el);
                const $row = $cb.closest('tr');
                const rawId = $cb.val();

                const storageKey = this.getStorageKey($row, rawId);

                if (this.selectedProducts.has(storageKey)) {
                    $cb.prop('checked', true);
                    const savedItem = this.selectedProducts.get(storageKey);

                    const $qtyInput = $row.find('.productbay-qty');
                    if ($qtyInput.length) {
                        $qtyInput.val(savedItem.quantity);

                        const min = parseInt($qtyInput.attr('min'), 10) || 1;
                        const max = parseInt($qtyInput.attr('max'), 10) || Infinity;
                        this.updateQtyButtons($qtyInput, savedItem.quantity, min, max);
                    }
                }
            });
            this.syncSelectAllCheckbox();
            this.updateBulkButton();
            this.restoreCartBadges(); // New: re-render badges & buttons
        }

        restoreCartBadges() {
            // Re-render variation badges and button text based on persisted cart quantities
            this.$tbody.find('tr').each((_, row) => {
                const $row = $(row);
                const productId = String($row.data('product-id'));
                const isVariable = $row.find('.productbay-variable-wrap').length > 0;

                if (isVariable) {
                    // It's a variable product. Only render if feature enabled.
                    if (this.features.variationBadges !== false) {
                        this.renderVariationBadges(productId);
                    }
                } else {
                    // Simple product - restore checkmark on button
                    const cartKey = String(productId);
                    const existing = this.cartQuantities.get(cartKey);
                    const $btn = $row.find('.productbay-btn-addtocart');
                    if ($btn.length) {
                        if (!$btn.data('original-text')) {
                            $btn.data('original-text', $btn.text());
                        }
                        const originalLabel = $btn.data('original-text');
                        
                        if (existing) {
                            const checkSvg = '<svg class="productbay-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                            $btn.html((originalLabel || $btn.text()) + ` <span class="productbay-added-badge">(${checkSvg} ${existing.quantity})</span>`);
                        } else {
                            $btn.html(originalLabel);
                        }
                    }
                }
            });
        }

        syncSelectAllCheckbox() {
            const $checkboxes = this.$tbody.find('.productbay-select-product:not(:disabled)');
            const $checked = $checkboxes.filter(':checked');
            this.$selectAll.prop('checked', $checkboxes.length > 0 && $checkboxes.length === $checked.length);
        }

        // ── Remove Items ────────────────────────────────────────────────

        handleClearAll(e) {
            e.preventDefault();
            this.selectedProducts.clear();
            this.$tbody.find('.productbay-select-product').prop('checked', false);
            this.$tbody.find('.productbay-remove-item').remove();
            this.$selectAll.prop('checked', false);
            this.updateBulkButton();
            this.saveSelectionsToStorage();
        }

        // ── Selection ───────────────────────────────────────────────────

        toggleSelectAll(e) {
            const isChecked = $(e.target).is(':checked');
            // Only toggle non-disabled checkboxes
            this.$tbody.find('.productbay-select-product:not(:disabled)').each((_, el) => {
                const $cb = $(el);
                const wasChecked = $cb.is(':checked');
                $cb.prop('checked', isChecked);
                // Trigger change only if state actually changed
                if (wasChecked !== isChecked) {
                    $cb.trigger('change');
                }
            });
            this.saveSelectionsToStorage();
        }

        handleRowSelect(e) {
            const $checkbox = $(e.target);
            if ($checkbox.is(':disabled')) return;

            const $row = $checkbox.closest('tr');
            const rawId = $checkbox.val(); // Original input value
            let id = rawId; // Product ID (or variation ID if nested/popup)
            
            // Read price directly from attribute to bypass jQuery's one-time data caching, 
            // since inline dropdowns dynamically update the checkbox data-price attribute on change!
            const price = parseFloat($checkbox.attr('data-price') || $checkbox.data('price') || 0);

            if ($checkbox.is(':checked')) {
                // Read current quantity from the row
                const $qtyInput = $row.find('.productbay-qty');
                const quantity = $qtyInput.length ? parseInt($qtyInput.val(), 10) || 1 : 1;

                // Read variation data if variable product
                const $variableWrap = $row.find('.productbay-variable-wrap');
                let variationId = 0;
                let attributes = {};
                let currentPrice = price;

                // 1. Standard Inline Dropdown Method
                if ($variableWrap.length) {
                    variationId = parseInt($variableWrap.find('.productbay-variation-id').val(), 10) || 0;
                    $variableWrap.find('.productbay-variation-select').each(function () {
                        const name = $(this).data('attribute-name');
                        attributes[name] = $(this).val();
                    });
                    // Use variation price if available
                    const varPrice = $variableWrap.data('current-variation-price');
                    if (varPrice) currentPrice = parseFloat(varPrice);
                }
                // 2. Pro Plugin Nested/Popup Method
                else if ($row.attr('data-parent-id')) {
                    const parentId = $row.attr('data-parent-id');
                    // For simple grouped children, they don't have variations, but they are their *own* products.
                    // For variations, the row's ID is the variation_id, and the parent is the product_id.
                    // The backend sets `data-product-type="simple"` for grouped children, so let's check it.
                    const pType = $row.attr('data-product-type') || 'variation';

                    if (pType !== 'simple') {
                        variationId = parseInt(id, 10);
                        id = parentId; // The actual WC cart product_id must be the parent
                    }

                    const attrData = $row.attr('data-attributes');
                    if (attrData) {
                        try {
                            attributes = JSON.parse(attrData);
                        } catch (e) { }
                    }
                }

                let storageKey = this.getStorageKey($row, rawId);

                let name = $row.find('.productbay-product-title').text().trim();
                let img = $row.find('img').first().attr('src');

                // Fallback for Pro Variation/Grouped Popup rows
                if (!name) {
                    name = $row.find('.productbay-pro-popup-col-name').text().trim();
                }

                // If this is an inline Grouped dropdown, extract the actual child name instead of parent
                const $groupedChildOption = $row.find(`.productbay-grouped-child-select option[value="${rawId}"]`);
                if ($groupedChildOption.length && rawId.indexOf(',') === -1) {
                    // It's a single child option
                    name = $groupedChildOption.text().trim();
                }

                // Fallback for missing image (use parent image from main table)
                if (!img && $row.attr('data-parent-id')) {
                    const parentId = $row.attr('data-parent-id');
                    img = this.$tbody.find(`tr[data-product-id="${parentId}"]`).find('img').first().attr('src');
                    if (!name) {
                        name = this.$tbody.find(`tr[data-product-id="${parentId}"]`).find('.productbay-product-title').text().trim() + ' - Variation';
                    }
                }

                this.selectedProducts.set(storageKey, {
                    productId: id, // Explicitly store productId since key might be compound
                    parentId: $row.attr('data-parent-id') || null,
                    quantity,
                    price: currentPrice,
                    variationId,
                    attributes,
                    productType: $row.data('product-type') || 'simple',
                    name: name || '',
                    img: img || ''
                });
            } else {
                // Determine the correct key to delete
                const storageKey = this.getStorageKey($row, rawId);

                this.selectedProducts.delete(storageKey);
                this.$selectAll.prop('checked', false);
            }

            this.updateBulkButton();
            this.saveSelectionsToStorage();
        }

        // ── Quantity ────────────────────────────────────────────────────

        handleQuantityChange(e) {
            const $input = $(e.target);
            const $row = $input.closest('tr');
            const $checkbox = $row.find('.productbay-select-product');
            const id = $checkbox.val();

            const min = parseInt($input.attr('min'), 10) || 1;
            const max = parseInt($input.attr('max'), 10) || Infinity;
            let val = parseInt($input.val(), 10);

            // Handle NaN / empty
            if (isNaN(val) || val < min) val = min;
            if (max !== Infinity && val > max) val = max;

            $input.val(val);
            this.updateQtyButtons($input, val, min, max);

            if (!$checkbox.is(':checked')) {
                return; // Do not sync quantity to bulk cart if this row is unchecked
            }

            // Get the accurate storage key (handles variables/grouped dynamically)
            const storageKey = this.getStorageKey($row, id);

            // Update selected product if checked
            if (this.selectedProducts.has(storageKey)) {
                const item = this.selectedProducts.get(storageKey);
                item.quantity = val;
                this.updateBulkButton();
                this.saveSelectionsToStorage();
            }
        }

        getStorageKey($row, rawId) {
            let id = rawId;
            let variationId = 0;
            const $variableWrap = $row.find('.productbay-variable-wrap');

            if ($variableWrap.length) {
                variationId = parseInt($variableWrap.find('.productbay-variation-id').val(), 10) || 0;
            } else if ($row.attr('data-parent-id')) {
                const parentId = $row.attr('data-parent-id');
                const pType = $row.attr('data-product-type') || 'variation';
                if (pType !== 'simple') {
                    variationId = parseInt(id, 10);
                    id = parentId;
                }
            }

            if (variationId) {
                return id + '_' + variationId;
            }
            return id;
        }

        /**
         * Clamp on blur — enforces final value when user leaves the field
         */
        handleQuantityBlur(e) {
            this.handleQuantityChange(e);
        }

        handleQtyMinus(e) {
            const $btn = $(e.currentTarget);
            const $input = $btn.closest('.productbay-qty-wrap').find('.productbay-qty');
            const min = parseInt($input.attr('min'), 10) || 1;
            let val = parseInt($input.val(), 10);
            if (isNaN(val)) val = min;
            if (val > min) {
                $input.val(val - 1).trigger('input');
            }
        }

        handleQtyPlus(e) {
            const $btn = $(e.currentTarget);
            const $input = $btn.closest('.productbay-qty-wrap').find('.productbay-qty');
            const max = parseInt($input.attr('max'), 10) || Infinity;
            let val = parseInt($input.val(), 10);
            if (isNaN(val)) val = 0;
            if (val < max) {
                $input.val(val + 1).trigger('input');
            }
        }

        /**
         * Disable the ▲/▼ buttons when at stock limits
         */
        updateQtyButtons($input, val, min, max) {
            const $wrap = $input.closest('.productbay-qty-wrap');
            $wrap.find('.productbay-qty-minus').prop('disabled', val <= min);
            $wrap.find('.productbay-qty-plus').prop('disabled', max !== Infinity && val >= max);
        }

        // ── Variation Selection ─────────────────────────────────────────

        handleVariationChange(e) {
            const $select = $(e.target);
            const $wrap = $select.closest('.productbay-variable-wrap');
            const $row = $wrap.closest('tr');
            const $btn = $wrap.find('.productbay-btn-addtocart');
            const $variationIdInput = $wrap.find('.productbay-variation-id');
            const $priceDisplay = $wrap.find('.productbay-variation-price');
            const $checkbox = $row.find('.productbay-select-product');
            const $qtyInput = $wrap.find('.productbay-qty');
            const variations = $wrap.data('product-variations') || [];
            const id = $checkbox.val();

            // Collect current selections
            const selected = {};
            let allSelected = true;
            $wrap.find('.productbay-variation-select').each(function () {
                const name = $(this).data('attribute-name');
                const val = $(this).val();
                selected[name] = val;
                if (!val) allSelected = false;
            });

            if (!allSelected) {
                // Variation incomplete: disable button + checkbox
                $btn.prop('disabled', true);
                $variationIdInput.val('');
                $priceDisplay.html('');
                $wrap.removeData('current-variation-price');

                // Disable checkbox and uncheck if was checked
                $checkbox.prop('disabled', true);
                if ($checkbox.is(':checked')) {
                    $checkbox.prop('checked', false);
                    this.selectedProducts.delete(id);
                    this.updateBulkButton();
                }
                return;
            }

            // Find matching variation
            const match = variations.find(v => {
                return Object.keys(selected).every(attr => {
                    const vAttr = v.attributes[attr];
                    return vAttr === '' || vAttr === selected[attr];
                });
            });

            if (match && match.is_purchasable && match.is_in_stock) {
                // Variation valid: enable button + checkbox (like a simple product)
                $btn.prop('disabled', false);
                $variationIdInput.val(match.variation_id);
                $priceDisplay.html(match.price_html);
                $wrap.data('current-variation-price', match.display_price);

                // Update quantity max based on variation stock
                if (match.max_qty && match.max_qty !== '') {
                    $qtyInput.attr('max', match.max_qty);
                } else {
                    $qtyInput.removeAttr('max');
                }

                // Enable the row checkbox
                $checkbox.prop('disabled', false);
                $checkbox.data('price', match.display_price);

                // Update bulk selection if already checked
                if (this.selectedProducts.has(id)) {
                    const item = this.selectedProducts.get(id);
                    item.variationId = match.variation_id;
                    item.price = match.display_price;
                    item.attributes = selected;
                    this.updateBulkButton();
                    this.saveSelectionsToStorage();
                }

                // Update button for the new variation — check if THIS variation was already added
                if (!$btn.data('original-text')) {
                    $btn.data('original-text', $btn.text());
                }
                const originalLabel = $btn.data('original-text');

                const cartKey = this.buildCartKey($wrap.data('product-id'), match.variation_id, selected);
                const existing = this.cartQuantities.get(cartKey);
                if (existing) {
                    const checkSvg = '<svg class="productbay-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    $btn.html((originalLabel || $btn.text()) + ` <span class="productbay-added-badge">(${checkSvg} ${existing.quantity})</span>`);
                } else {
                    $btn.html(originalLabel || $btn.text());
                }
            } else {
                // Variation invalid/out-of-stock: disable button + checkbox
                $btn.prop('disabled', true);
                $variationIdInput.val('');
                $priceDisplay.html('');
                $wrap.removeData('current-variation-price');

                $checkbox.prop('disabled', true);
                if ($checkbox.is(':checked')) {
                    $checkbox.prop('checked', false);
                    this.selectedProducts.delete(id);
                    this.updateBulkButton();
                    this.saveSelectionsToStorage();
                }
            }
        }

        // ── Single Add to Cart ──────────────────────────────────────────

        handleSingleAddToCart(e) {
            if (this.features.cartEnabled === false) {
                // If AJAX is disabled, let the form submit naturally
                return;
            }
            e.preventDefault();
            const $btn = $(e.currentTarget);
            if ($btn.is(':disabled')) return;

            const $wrap = $btn.closest('.productbay-btn-cell');
            const productIdStr = String($btn.data('product-id') || $btn.closest('.productbay-variable-wrap').data('product-id') || $btn.closest('tr').data('product-id'));
            const productIds = productIdStr.split(',');

            const $qtyInput = $wrap.find('.productbay-qty');
            const quantity = $qtyInput.length ? parseInt($qtyInput.val(), 10) || 1 : 1;

            // Variable product: get variation data
            let variationId = 0;
            const attributes = {};
            const $variableWrap = $wrap.closest('.productbay-variable-wrap');
            if ($variableWrap.length) {
                variationId = parseInt($variableWrap.find('.productbay-variation-id').val(), 10) || 0;
                if (!variationId) {
                    alert('Please select product options first.');
                    return;
                }
                $variableWrap.find('.productbay-variation-select').each(function () {
                    attributes[$(this).data('attribute-name')] = $(this).val();
                });
            }

            // Store original label (without any existing checkmark badge)
            if (!$btn.data('original-text')) {
                $btn.data('original-text', $btn.text());
            }
            const originalLabel = $btn.data('original-text');
            $btn.text('Adding...').prop('disabled', true);

            const itemsPayload = productIds.map(id => ({
                product_id: parseInt(id.trim(), 10),
                quantity: quantity,
                variation_id: variationId,
                attributes: attributes
            }));

            $.ajax({
                url: productbay_frontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'productbay_bulk_add_to_cart',
                    nonce: productbay_frontend.nonce,
                    items: itemsPayload
                },
                success: (response) => {
                    if (response.success) {
                        $(document.body).trigger('wc_fragment_refresh');

                        // Track accumulated cart quantity
                        itemsPayload.forEach(itemInfo => {
                            const pId = itemInfo.product_id;
                            const cartKey = this.buildCartKey(pId, itemInfo.variation_id, itemInfo.attributes);
                            const prevEntry = this.cartQuantities.get(cartKey);
                            const prevQty = prevEntry ? prevEntry.quantity : 0;
                            const newQty = prevQty + quantity;

                            // We store an object for all cart quantities to support variation badge generation easily
                            this.cartQuantities.set(cartKey, {
                                quantity: newQty,
                                variationId: itemInfo.variation_id,
                                attributes: Object.assign({}, itemInfo.attributes),
                                productId: pId
                            });
                        });


                        // Update button text with SVG checkmark and quantity
                        const checkSvg = '<svg class="productbay-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                        // For display on the button, we sum all quantities added
                        const totalAddedThisTime = itemsPayload.length * quantity;
                        $btn.html(originalLabel + ' <span class="productbay-added-badge">(' + checkSvg + ' +' + totalAddedThisTime + ')</span>');
                        $btn.prop('disabled', false);

                        if (variationId && this.features.variationBadges !== false) {
                            this.renderVariationBadges(productIds[0]); // fallback legacy call
                        }

                    } else {
                        const msg = response.data?.errors?.join('\n') || response.data?.message || 'Error adding product.';
                        alert(msg);
                        $btn.text(originalLabel).prop('disabled', false);
                    }
                },
                error: () => {
                    alert('Server error');
                    $btn.text(originalLabel).prop('disabled', false);
                }
            });
        }

        // ── Bulk Add to Cart ────────────────────────────────────────────

        updateBulkButton() {
            const count = this.selectedProducts.size;
            let totalItems = 0;
            let totalPrice = 0;

            const parentCounts = new Map();

            this.selectedProducts.forEach(item => {
                totalItems += item.quantity;
                totalPrice += item.quantity * item.price;

                const pId = item.parentId || item.productId;
                if (pId) {
                    parentCounts.set(String(pId), (parentCounts.get(String(pId)) || 0) + 1);
                }
            });

            if (count > 0) {
                const text = `Add ${totalItems} item${totalItems > 1 ? 's' : ''} for ${formatPrice(totalPrice)}`;
                this.$bulkBtn.text(text).prop('disabled', false);
                if (this.features.clearAllButton !== false && !this.$wrapper.find('.productbay-btn-clear-all').length) {
                    this.$wrapper.find('.productbay-btn-group').append('<button class="productbay-button productbay-btn-clear-all" title="Warning: This will clear all your active selections">Clear</button>');
                }

                if (this.features.selectedItemsPanel?.enabled !== false) {
                    this.$wrapper.find('.productbay-btn-panel').prop('disabled', false).find('.productbay-panel-count').text(count);
                }
            } else {
                this.$bulkBtn.html(this.originalBulkBtnHtml || '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="display:inline-block;vertical-align:middle"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> Add to Cart').prop('disabled', true);
                this.$wrapper.find('.productbay-btn-clear-all').remove();
                this.$wrapper.find('.productbay-btn-panel').prop('disabled', true).find('.productbay-panel-count').text(0);
            }

            this.renderSelectedItemsPopup();
            this.updateParentRowBadges(parentCounts);
        }

        updateParentRowBadges(parentCounts) {
            this.$tbody.find('.productbay-pro-btn-popup, .productbay-pro-btn-nested').each((i, btn) => {
                const $btn = $(btn);
                const pId = String($btn.data('product-id'));
                const count = parentCounts.get(pId) || 0;

                $btn.find('.productbay-pro-btn-badge').remove();

                if (count > 0) {
                    const checkSvg = '<svg class="productbay-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="12" height="12" style="display:inline-block;vertical-align:-1px;margin-right:2px"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    $btn.append(`<span class="productbay-pro-btn-badge" style="margin-left: 6px; font-weight: 600; color: #15803d; background: #dcfce7; padding: 2px 6px; border-radius: 12px; font-size: 12px; display: inline-flex; align-items: center;">${checkSvg}${count}</span>`);
                }
            });
        }

        handleBulkAddToCart(e) {
            e.preventDefault();
            if (this.selectedProducts.size === 0) return;

            // Validate variable products have variations selected
            const invalidItems = [];
            this.selectedProducts.forEach((item, id) => {
                if (item.productType === 'variable' && !item.variationId) {
                    invalidItems.push(id);
                }
            });

            if (invalidItems.length > 0) {
                alert('Please select options for all variable products before adding to cart.');
                return;
            }

            const $btn = this.$bulkBtn;
            const originalText = $btn.text();
            $btn.text('Adding...').prop('disabled', true);

            // Build items array
            const items = [];
            this.selectedProducts.forEach((item, id) => {
                const ids = String(item.productId || id).split(',');
                ids.forEach(pId => {
                    items.push({
                        product_id: parseInt(pId.trim(), 10),
                        quantity: item.quantity,
                        variation_id: item.variationId || 0,
                        attributes: item.attributes || {}
                    });
                });
            });

            $.ajax({
                url: productbay_frontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'productbay_bulk_add_to_cart',
                    nonce: productbay_frontend.nonce,
                    items: items
                },
                success: (response) => {
                    if (response.success) {
                        $btn.text('Added!');
                        $(document.body).trigger('wc_fragment_refresh');
                        if (this.features.cartEnabled === false) {
                            window.location.reload();
                        }

                        // Show warnings if any
                        if (response.data.warnings && response.data.warnings.length > 0) {
                            setTimeout(() => {
                                alert('Some items had issues:\n' + response.data.warnings.join('\n'));
                            }, 500);
                        }

                        // Update variation badges for variable products we just added
                        this.selectedProducts.forEach((item, id) => {
                            if (item.productType === 'variable') {
                                const cartKey = this.buildCartKey(id, item.variationId, item.attributes);
                                const prevEntry = this.cartQuantities.get(cartKey);
                                const prevQty = prevEntry ? prevEntry.quantity : 0;
                                const newQty = prevQty + item.quantity;
                                this.cartQuantities.set(cartKey, {
                                    quantity: newQty,
                                    variationId: item.variationId,
                                    attributes: Object.assign({}, item.attributes),
                                    productId: id
                                });

                                if (item.variationId && this.features.variationBadges !== false) {
                                    this.renderVariationBadges(id);
                                }
                            } else {
                                const cartKey = String(id);
                                const prevEntry = this.cartQuantities.get(cartKey);
                                const prevQty = prevEntry ? prevEntry.quantity : 0;
                                const newQty = prevQty + item.quantity;
                                this.cartQuantities.set(cartKey, {
                                    quantity: newQty,
                                    productId: id
                                });
                            }

                            // Let's also refresh single add to cart buttons for items added in bulk
                            let $row = this.$tbody.find(`tr[data-product-id="${id}"]`);
                            if (!$row.length) {
                                $row = this.$tbody.find(`.productbay-select-product[value="${id}"]`).closest('tr');
                            }
                            const $btn = $row.find('.productbay-btn-addtocart');
                            if ($btn.length) {
                                if (!$btn.data('original-text')) {
                                    $btn.data('original-text', $btn.text());
                                }
                                const originalLabel = $btn.data('original-text');

                                const cartKey = this.buildCartKey(id, item.variationId, item.attributes);
                                const existing = this.cartQuantities.get(cartKey);
                                if (existing) {
                                    const checkSvg = '<svg class="productbay-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                                    $btn.html((originalLabel || $btn.text()) + ` <span class="productbay-added-badge">(${checkSvg} ${existing.quantity})</span>`);
                                }
                            }
                        });


                        setTimeout(() => {
                            this.selectedProducts.clear();
                            this.$tbody.find('.productbay-select-product').prop('checked', false);
                            this.$tbody.find('.productbay-remove-item').remove();
                            this.$selectAll.prop('checked', false);
                            this.updateBulkButton();
                            this.saveSelectionsToStorage();
                        }, 2000);
                    } else {
                        const msg = response.data?.errors?.join('\n') || response.data?.message || 'Error adding products.';
                        alert(msg);
                        $btn.text(originalText).prop('disabled', false);
                    }
                },
                error: () => {
                    alert('Server error');
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        }

        getAddedVariationsForProduct(productId) {
            const results = [];
            this.cartQuantities.forEach((data, key) => {
                if (String(data.productId) === String(productId) && data.variationId) {
                    // Build human-readable label from attributes
                    const attrParts = [];
                    if (data.attributes) {
                        Object.entries(data.attributes).forEach(([key, val]) => {
                            if (val) {
                                // Simple text processing for attribute keys
                                const label = key.replace('attribute_pa_', '').replace('attribute_', '').replace(/-/g, ' ');
                                attrParts.push(val); // Only pushing the selected value for brevity.
                            }
                        });
                    }
                    results.push({
                        key: key,
                        variationId: data.variationId,
                        quantity: data.quantity,
                        label: attrParts.join(' / ') || 'Default'
                    });
                }
            });
            return results;
        }

        renderVariationBadges(productId) {
            const $row = this.$tbody.find(`tr[data-product-id="${productId}"]`);
            const $wrap = $row.find('.productbay-variable-wrap');
            if (!$wrap.length) return;

            const badges = this.getAddedVariationsForProduct(productId);
            if (badges.length === 0) return;

            // Remove existing badges container
            $wrap.find('.productbay-variation-badges').remove();

            let html = '<div class="productbay-variation-badges">';
            badges.forEach(b => {
                html += `<span class="productbay-variation-badge">
                    <svg class="productbay-check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="10" height="10" style="display:inline;vertical-align:middle;margin-right:2px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    ${b.label} (×${b.quantity})
                </span>`;
            });
            html += '</div>';

            $wrap.find('.productbay-btn-cell').before(html);
        }

        // ── Selected Items Popup ────────────────────────────────────────

        toggleSelectedItemsPopup(e) {
            e.preventDefault();
            const $popup = this.$wrapper.find('.productbay-bulk-actions .productbay-selected-popup');
            if ($popup.length && $popup.is(':visible')) {
                this.closeSelectedItemsPopup(e);
            } else {
                this.renderSelectedItemsPopup();
                this.$wrapper.find('.productbay-bulk-actions .productbay-selected-popup').addClass('is-open');
            }
        }

        closeSelectedItemsPopup(e) {
            e?.preventDefault();
            this.$wrapper.find('.productbay-bulk-actions .productbay-selected-popup').removeClass('is-open');
        }

        handlePopupRemove(e) {
            e.preventDefault();
            const id = $(e.currentTarget).data('product-id');
            let $row = this.$tbody.find(`tr[data-product-id="${id}"]`);
            if (!$row.length) {
                $row = this.$tbody.find(`.productbay-select-product[value="${id}"]`).closest('tr');
            }

            if ($row.length) {
                const $cb = $row.find('.productbay-select-product');
                $cb.prop('checked', false).trigger('change');
            } else {
                this.selectedProducts.delete(String(id));
                this.updateBulkButton();
                this.saveSelectionsToStorage();
            }
        }

        handlePopupAddAll(e) {
            e.preventDefault();
            // Trigger the main bulk add to cart
            this.$bulkBtn.trigger('click');
        }

        renderSelectedItemsPopup() {
            if (this.features.selectedItemsPanel?.enabled === false) return;

            const $container = this.$wrapper.find('.productbay-bulk-actions');
            if (!$container.length) return; // If toolbar missing

            let $popup = $container.find('.productbay-selected-popup');
            if (!$popup.length) {
                $container.append('<div class="productbay-selected-popup"></div>');
                $popup = $container.find('.productbay-selected-popup');
            }

            if (this.selectedProducts.size === 0) {
                $popup.removeClass('is-open');
                return;
            }

            let totalPrice = 0;
            let html = '<div class="productbay-popup-header">';
            html += `<strong>Selected Items <span class="productbay-popup-count">(${this.selectedProducts.size})</span></strong>`;
            html += '<button class="productbay-popup-close" title="Close popup">&times;</button>';
            html += '</div>';
            html += '<div class="productbay-popup-items">';

            this.selectedProducts.forEach((item, id) => {
                const lineTotal = item.quantity * item.price;
                totalPrice += lineTotal;

                // Get product info from the row (fallback)
                const $row = this.$tbody.find(`tr[data-product-id="${id}"]`);
                const name = item.name || $row.find('.productbay-product-title').text().trim() || `Product #${id}`;
                const img = item.img || $row.find('img').first().attr('src') || '';

                html += `<div class="productbay-popup-item" data-id="${id}">`;
                if (img) html += `<img src="${img}" class="productbay-popup-thumb" />`;
                html += `<div class="productbay-popup-info">`;
                html += `<div class="productbay-popup-name">${name}</div>`;
                if (item.variationId) {
                    const attrs = Object.values(item.attributes || {}).filter(Boolean).join(' / ');
                    html += `<div class="productbay-popup-variation">${attrs}</div>`;
                }
                html += `<div class="productbay-popup-pricing">`;
                html += `${item.quantity} &times; ${formatPrice(item.price)} = <strong>${formatPrice(lineTotal)}</strong>`;
                html += `</div></div>`;
                html += `<button class="productbay-popup-remove" data-product-id="${id}" title="Remove item">&times;</button>`;
                html += `</div>`;
            });

            html += '</div>';
            html += `<div class="productbay-popup-footer">`;
            html += `<div class="productbay-popup-total">Total: <strong>${formatPrice(totalPrice)}</strong></div>`;
            html += `<button class="productbay-popup-add-all productbay-button">Add All to Cart</button>`;
            html += `</div>`;

            // Preserve exactly 'is-open' state while updating HTML string
            const isOpen = $popup.hasClass('is-open');
            $popup.html(html);
            if (isOpen) $popup.addClass('is-open');
        }
    }
	// ─── Grouped Product Inline Select Handlers ─────────────────────────────────────
	// When a child product is selected: enable qty/cart/checkbox.
	// 'Select All' option => enable checkbox with all child IDs.
	document.body.addEventListener('change', (e) => {
		if (!e.target.classList.contains('productbay-grouped-child-select')) return;

		const select = e.target;
		const wrap = select.closest('.productbay-grouped-inline-wrap');
		if (!wrap) return;

		const qtyInput = wrap.querySelector('.productbay-qty') || wrap.querySelector('input[name="quantity"]');
		const addBtn = wrap.querySelector('.productbay-grouped-add-btn');
		const priceSpan = wrap.querySelector('.productbay-grouped-inline-price');
		const hiddenInput = wrap.querySelector('.productbay-grouped-hidden-id');
		const row = select.closest('tr');
		const rowCheckbox = row ? row.querySelector('.productbay-col-select .productbay-select-product') : null;

		const selectedId = select.value;
		const childrenData = JSON.parse(wrap.getAttribute('data-children') || '[]');

		if (!selectedId) {
			// No selection — disable controls
			if (qtyInput) qtyInput.disabled = true;
			if (addBtn) { addBtn.disabled = true; addBtn.setAttribute('data-product-id', ''); }
			if (hiddenInput) hiddenInput.value = '';
			if (priceSpan) priceSpan.innerHTML = '';
			if (rowCheckbox) { rowCheckbox.disabled = true; rowCheckbox.checked = false; rowCheckbox.dispatchEvent(new Event('change', { bubbles: true })); }
			wrap.querySelectorAll('.productbay-qty-plus, .productbay-qty-minus').forEach(b => b.disabled = true);
			return;
		}

		if (selectedId === '__all__') {
			// Select All — enable qty/cart with all child IDs
			const allIds = childrenData.map(c => String(c.id));
			if (addBtn) { addBtn.disabled = false; addBtn.setAttribute('data-product-id', allIds.join(',')); }
			if (hiddenInput) hiddenInput.value = allIds.join(',');
			if (qtyInput) qtyInput.disabled = false;
			wrap.querySelectorAll('.productbay-qty-plus, .productbay-qty-minus').forEach(b => b.disabled = false);
			if (priceSpan) priceSpan.innerHTML = '';
			if (rowCheckbox) {
				rowCheckbox.disabled = false;
				rowCheckbox.value = allIds.join(',');
				const totalPrice = childrenData.reduce((sum, c) => sum + parseFloat(c.price || 0), 0);
				rowCheckbox.setAttribute('data-price', totalPrice);
				rowCheckbox.setAttribute('data-multi', '1');
				rowCheckbox.checked = true;
				// Trigger change to update bulk selection
				rowCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
			}
			return;
		}

		// Single child selected — enable all controls
		const child = childrenData.find(c => String(c.id) === selectedId);
		if (child) {
			if (priceSpan) priceSpan.innerHTML = child.price_html;
			if (addBtn) { addBtn.disabled = false; addBtn.setAttribute('data-product-id', String(child.id)); }
			if (hiddenInput) hiddenInput.value = String(child.id);
			if (qtyInput) qtyInput.disabled = false;
			wrap.querySelectorAll('.productbay-qty-plus, .productbay-qty-minus').forEach(b => b.disabled = false);
			if (rowCheckbox) {
				rowCheckbox.disabled = false;
				rowCheckbox.value = String(child.id);
				rowCheckbox.setAttribute('data-price', String(child.price));
				rowCheckbox.removeAttribute('data-multi');
				rowCheckbox.checked = true;
				rowCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
			}
		}
	});

	// Grouped Product Add To Cart (Native integration without reloading if possible, but fallback to bulk add)
	document.body.addEventListener('click', (e) => {
		const btn = e.target.closest('.productbay-grouped-add-btn');
		if (!btn || btn.disabled) return;

		const idsStr = btn.getAttribute('data-product-id');
		if (!idsStr) return;
		const ids = idsStr.split(',');

		// If AJAX is disabled the button type is submit.
		if (btn.getAttribute('type') === 'submit') {
			if (ids.length > 1) {
				// We can't handle multiple native WP add_to_cart params cleanly inside generic POST.
				// We'll intercept and force the Bulk add-to-cart handler via AJAX, then redirect.
				e.preventDefault();
				const wrap = btn.closest('.productbay-grouped-inline-wrap');
				const qtyInput = wrap.querySelector('input[name="quantity"]');
				const quantity = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
				
				const items = ids.map(id => ({
					product_id: parseInt(id, 10),
					quantity: quantity,
					variation_id: 0,
					attributes: {}
				}));

				btn.innerHTML = 'Adding...';
				btn.disabled = true;

				window.jQuery.ajax({
					url: window.productbay_frontend.ajaxurl,
					type: 'POST',
					data: {
						action: 'productbay_bulk_add_to_cart',
						nonce: window.productbay_frontend.nonce,
						items: items
					},
					success: (response) => {
						if (response.success) {
							window.location.reload(); // Reload since ajax is visually disabled
						} else {
							alert(response.data?.errors?.join('\n') || 'Error');
							btn.disabled = false;
						}
					}
				});
			} else {
				// Single product, native submit is fine. Do nothing with e.preventDefault()
			}
			return;
		}

		e.preventDefault(); // It's an ajax button

		const wrap = btn.closest('.productbay-grouped-inline-wrap');
		const qtyInput = wrap.querySelector('.productbay-qty');
		const quantity = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;

		const items = ids.map(id => ({
			product_id: parseInt(id, 10),
			quantity: quantity,
			variation_id: 0,
			attributes: {}
		}));

		if (!btn.hasAttribute('data-original-text')) {
			btn.setAttribute('data-original-text', btn.innerHTML);
		}
		const originalText = btn.getAttribute('data-original-text');

		btn.innerHTML = 'Adding...';
		btn.disabled = true;

		const $ = window.jQuery;
		if (!$) return;

		$.ajax({
			url: window.productbay_frontend.ajaxurl,
			type: 'POST',
			data: {
				action: 'productbay_bulk_add_to_cart',
				nonce: window.productbay_frontend.nonce,
				items: items
			},
			success: (response) => {
				if (response.success) {
					btn.innerHTML = 'Added ✓';
					$(document.body).trigger('wc_fragment_refresh');
				} else {
					btn.innerHTML = 'Error';
					alert(response.data?.errors?.join('\n') || 'Error adding to cart');
				}
			},
			error: () => {
				btn.innerHTML = 'Error';
			},
			complete: () => {
				setTimeout(() => {
					if (btn.innerHTML === 'Added ✓' || btn.innerHTML === 'Error') {
						btn.innerHTML = originalText;
						btn.disabled = false;
					}
				}, 2000);
			}
		});
	});

    // Initialize on document ready
    $(document).ready(function () {
        $('.productbay-wrapper').each(function () {
            new ProductBayTable(this);
        });
    });

})(jQuery);
