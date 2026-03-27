// Main JavaScript for Cotizacion System
// Works with or without jQuery

(function() {
    'use strict';

    // Check if jQuery is available
    const hasJQuery = typeof $ !== 'undefined' && typeof $.fn !== 'undefined';

    // DOM Ready function that works with or without jQuery
    function domReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    // Initialize on DOM ready
    domReady(function() {
        // Initialize components that work without jQuery
        initializeTooltips();
        initializePopovers();

        // Initialize jQuery-dependent components only if jQuery is available
        if (hasJQuery) {
            initializeModals();
            initializeForms();
            initializeSearch();
            initializeDataTables();

            // Initialize modules
            if ($('.quotation-builder').length) {
                QuotationBuilder.init();
            }

            if ($('.customer-lookup').length) {
                CustomerLookup.init();
            }
        }
    });

    // Initialize Bootstrap tooltips (no jQuery needed)
    function initializeTooltips() {
        if (typeof bootstrap === 'undefined') return;
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Initialize Bootstrap popovers (no jQuery needed)
    function initializePopovers() {
        if (typeof bootstrap === 'undefined') return;
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }

    // Initialize modals (requires jQuery)
    function initializeModals() {
        if (!hasJQuery) return;

        $('.modal').on('shown.bs.modal', function() {
            $(this).find('input:text:first').focus();
        });

        $('.modal').on('hidden.bs.modal', function() {
            $(this).find('form')[0]?.reset();
            $(this).find('.is-invalid').removeClass('is-invalid');
            $(this).find('.invalid-feedback').remove();
        });
    }

    // Initialize forms (requires jQuery)
    function initializeForms() {
        if (!hasJQuery) return;

        $('.needs-validation').on('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            $(this).addClass('was-validated');
        });

        $('.auto-save').on('input change', debounce(function() {
            autoSaveForm(this);
        }, 1000));

        $('.currency-input').on('input', function() {
            formatCurrency(this);
        });

        $('.percentage-input').on('input', function() {
            formatPercentage(this);
        });
    }

    // Initialize search functionality (requires jQuery)
    function initializeSearch() {
        if (!hasJQuery) return;

        $('.search-input').on('input', debounce(function() {
            const searchTerm = $(this).val();
            performSearch(searchTerm);
        }, 300));
    }

    // Initialize DataTables (requires jQuery and DataTables)
    function initializeDataTables() {
        if (!hasJQuery || !$.fn.DataTable) return;

        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            }
        });
    }

    // Utility functions
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function formatCurrency(input) {
        let value = input.value.replace(/[^\d.]/g, '');
        if (value) {
            value = parseFloat(value).toFixed(2);
            input.value = value;
        }
    }

    function formatPercentage(input) {
        let value = input.value.replace(/[^\d.]/g, '');
        if (value) {
            value = Math.min(100, parseFloat(value)).toFixed(2);
            input.value = value;
        }
    }

    function autoSaveForm(element) {
        if (!hasJQuery) return;

        const form = $(element).closest('form');
        const formData = form.serialize();

        $.ajax({
            url: form.attr('action') || window.location.pathname,
            method: 'POST',
            data: formData + '&auto_save=1',
            success: function(response) {
                showNotification('Guardado automáticamente', 'success');
            },
            error: function() {
                showNotification('Error al guardar automáticamente', 'warning');
            }
        });
    }

    function performSearch(searchTerm) {
        if (!hasJQuery) return;

        $('.search-results').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>');

        $.ajax({
            url: '/api/search.php',
            method: 'GET',
            data: { q: searchTerm },
            success: function(response) {
                $('.search-results').html(response);
            },
            error: function() {
                $('.search-results').html('<div class="text-center text-muted">Error en la búsqueda</div>');
            }
        });
    }

    function showNotification(message, type) {
        type = type || 'info';

        const alertClass = 'alert-' + type;
        const iconClasses = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        const iconClass = iconClasses[type] || 'fa-info-circle';

        const alertHtml =
            '<div class="alert ' + alertClass + ' alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">' +
                '<i class="fas ' + iconClass + '"></i> ' + message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>';

        if (hasJQuery) {
            $('body').append(alertHtml);
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        } else {
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            setTimeout(function() {
                var alert = document.querySelector('.alert');
                if (alert) alert.remove();
            }, 5000);
        }
    }

    // Quotation Builder functionality (requires jQuery)
    var QuotationBuilder = {
        items: [],

        init: function() {
            if (!hasJQuery) return;
            this.bindEvents();
            this.calculateTotals();
        },

        bindEvents: function() {
            $(document).on('click', '.add-item-btn', this.addItem.bind(this));
            $(document).on('click', '.remove-item-btn', this.removeItem.bind(this));
            $(document).on('input', '.item-quantity, .item-price, .item-discount, .global-discount', this.calculateTotals.bind(this));
            $(document).on('change', '.product-select', this.onProductChange.bind(this));
        },

        addItem: function() {
            var self = this;
            var itemHtml =
                '<div class="item-row" data-item-index="' + this.items.length + '">' +
                    '<div class="row">' +
                        '<div class="col-md-4">' +
                            '<select class="form-select product-select" name="items[' + this.items.length + '][product_id]">' +
                                '<option value="">Seleccionar producto...</option>' +
                            '</select>' +
                            '<input type="text" class="form-control mt-2" name="items[' + this.items.length + '][description]" placeholder="Descripción">' +
                        '</div>' +
                        '<div class="col-md-2">' +
                            '<input type="number" class="form-control item-quantity" name="items[' + this.items.length + '][quantity]" placeholder="Cantidad" min="1" step="0.01">' +
                        '</div>' +
                        '<div class="col-md-2">' +
                            '<input type="number" class="form-control item-price" name="items[' + this.items.length + '][unit_price]" placeholder="Precio" min="0" step="0.01">' +
                        '</div>' +
                        '<div class="col-md-2">' +
                            '<input type="number" class="form-control item-discount" name="items[' + this.items.length + '][discount_percentage]" placeholder="Desc. %" min="0" max="100" step="0.01">' +
                        '</div>' +
                        '<div class="col-md-2">' +
                            '<div class="input-group">' +
                                '<span class="input-group-text">S/</span>' +
                                '<input type="text" class="form-control item-total" readonly>' +
                                '<button type="button" class="btn btn-danger remove-item-btn">' +
                                    '<i class="fas fa-trash"></i>' +
                                '</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            $('.items-container').append(itemHtml);
            this.items.push({});
            this.loadProducts();
        },

        removeItem: function(e) {
            var itemRow = $(e.target).closest('.item-row');
            var index = itemRow.data('item-index');

            itemRow.remove();
            this.items.splice(index, 1);
            this.reindexItems();
            this.calculateTotals();
        },

        reindexItems: function() {
            $('.item-row').each(function(index, element) {
                $(element).attr('data-item-index', index);
                $(element).find('[name*="items["]').each(function() {
                    var name = $(this).attr('name');
                    var newName = name.replace(/items\[\d+\]/, 'items[' + index + ']');
                    $(this).attr('name', newName);
                });
            });
        },

        onProductChange: function(e) {
            var productSelect = $(e.target);
            var productId = productSelect.val();

            if (productId) {
                this.loadProductDetails(productId, productSelect.closest('.item-row'));
            }
        },

        loadProductDetails: function(productId, itemRow) {
            var self = this;
            $.ajax({
                url: '/api/products.php',
                method: 'GET',
                data: { id: productId },
                success: function(response) {
                    var product = response.data;
                    itemRow.find('[name*="[description]"]').val(product.description);
                    itemRow.find('[name*="[unit_price]"]').val(product.regular_price);
                    self.calculateTotals();
                }
            });
        },

        loadProducts: function() {
            $.ajax({
                url: '/api/products.php',
                method: 'GET',
                success: function(response) {
                    var options = response.data.map(function(product) {
                        return '<option value="' + product.id + '">' + product.code + ' - ' + product.description + '</option>';
                    }).join('');

                    $('.product-select').html('<option value="">Seleccionar producto...</option>' + options);
                }
            });
        },

        calculateTotals: function() {
            var subtotal = 0;

            $('.item-row').each(function() {
                var quantity = parseFloat($(this).find('.item-quantity').val()) || 0;
                var price = parseFloat($(this).find('.item-price').val()) || 0;
                var discount = parseFloat($(this).find('.item-discount').val()) || 0;

                var lineSubtotal = quantity * price;
                var discountAmount = (lineSubtotal * discount) / 100;
                var lineTotal = lineSubtotal - discountAmount;

                $(this).find('.item-total').val(lineTotal.toFixed(2));
                subtotal += lineTotal;
            });

            var globalDiscount = parseFloat($('.global-discount').val()) || 0;
            var globalDiscountAmount = (subtotal * globalDiscount) / 100;
            var total = subtotal - globalDiscountAmount;

            $('.subtotal-amount').text(subtotal.toFixed(2));
            $('.global-discount-amount').text(globalDiscountAmount.toFixed(2));
            $('.total-amount').text(total.toFixed(2));
        }
    };

    // Customer lookup functionality (requires jQuery)
    var CustomerLookup = {
        init: function() {
            if (!hasJQuery) return;
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('input', '.dni-input', this.onDniInput.bind(this));
            $(document).on('input', '.ruc-input', this.onRucInput.bind(this));
            $(document).on('click', '.lookup-dni-btn', this.lookupDni.bind(this));
            $(document).on('click', '.lookup-ruc-btn', this.lookupRuc.bind(this));
        },

        onDniInput: function(e) {
            var dni = $(e.target).val().replace(/\D/g, '').substring(0, 8);
            $(e.target).val(dni);
        },

        onRucInput: function(e) {
            var ruc = $(e.target).val().replace(/\D/g, '').substring(0, 11);
            $(e.target).val(ruc);
        },

        lookupDni: function(e) {
            var self = this;
            var dni = $('.dni-input').val();
            if (dni.length !== 8) {
                showNotification('El DNI debe tener 8 dígitos', 'warning');
                return;
            }

            this.showLoading($(e.target));

            $.ajax({
                url: '/api/lookup.php',
                method: 'POST',
                data: { dni: dni, type: 'dni' },
                success: function(response) {
                    if (response.success) {
                        self.fillDniData(response.data);
                        showNotification('Datos encontrados correctamente', 'success');
                    } else {
                        showNotification(response.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Error al consultar DNI', 'error');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        lookupRuc: function(e) {
            var self = this;
            var ruc = $('.ruc-input').val();
            if (ruc.length !== 11) {
                showNotification('El RUC debe tener 11 dígitos', 'warning');
                return;
            }

            this.showLoading($(e.target));

            $.ajax({
                url: '/api/lookup.php',
                method: 'POST',
                data: { ruc: ruc, type: 'ruc' },
                success: function(response) {
                    if (response.success) {
                        self.fillRucData(response.data);
                        showNotification('Datos encontrados correctamente', 'success');
                    } else {
                        showNotification(response.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Error al consultar RUC', 'error');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        fillDniData: function(data) {
            $('[name="customer_name"]').val(data.nombre_completo);
            $('[name="contact_person"]').val(data.nombre_completo);
        },

        fillRucData: function(data) {
            $('[name="customer_name"]').val(data.razon_social);
            $('[name="contact_person"]').val(data.nombre_comercial);
            $('[name="address"]').val(this.formatAddress(data));
        },

        formatAddress: function(data) {
            var parts = [];
            if (data.direccion) parts.push(data.direccion);
            if (data.distrito) parts.push(data.distrito);
            if (data.provincia) parts.push(data.provincia);
            if (data.departamento) parts.push(data.departamento);
            return parts.join(', ');
        },

        showLoading: function(button) {
            button.prop('disabled', true);
            button.html('<i class="fas fa-spinner fa-spin"></i> Consultando...');
        },

        hideLoading: function() {
            $('.lookup-dni-btn').prop('disabled', false).html('<i class="fas fa-search"></i> Consultar DNI');
            $('.lookup-ruc-btn').prop('disabled', false).html('<i class="fas fa-search"></i> Consultar RUC');
        }
    };

    // Global functions for external use
    window.showNotification = showNotification;
    window.QuotationBuilder = QuotationBuilder;
    window.CustomerLookup = CustomerLookup;

})();
