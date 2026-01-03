(function($) {
    'use strict';
    
    $(document).ready(function() {
        var itemIndex = 1;
        
        // Print bill functionality
        $(document).on('click', '.brb-print-bill', function(e) {
            e.preventDefault();
            window.print();
        });
        
        // Add item (frontend form)
        $(document).on('click', '.brb-add-item-frontend', function(e) {
            e.preventDefault();
            
            var newRow = $('.brb-item-row-frontend').first().clone();
            newRow.find('input').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[\d+\]/, '[' + itemIndex + ']');
                    $(this).attr('name', name);
                    if ($(this).hasClass('brb-item-quantity')) {
                        $(this).val('1');
                    } else {
                        $(this).val('');
                    }
                }
            });
            newRow.find('.brb-item-total').text(brb_format_currency(0));
            
            $('#brb-items-tbody-frontend').append(newRow);
            itemIndex++;
        });
        
        // Remove item (frontend form)
        $(document).on('click', '.brb-remove-item-frontend', function(e) {
            e.preventDefault();
            
            if ($('.brb-item-row-frontend').length > 1) {
                $(this).closest('.brb-item-row-frontend').remove();
                calculateTotalFrontend();
            } else {
                alert('At least one item is required.');
            }
        });
        
        // Calculate item total (frontend)
        $(document).on('input', '.brb-item-quantity, .brb-item-rate', function() {
            var row = $(this).closest('.brb-item-row-frontend');
            var quantity = parseFloat(row.find('.brb-item-quantity').val()) || 0;
            var rate = parseFloat(row.find('.brb-item-rate').val()) || 0;
            var total = quantity * rate;
            
            row.find('.brb-item-total').text(brb_format_currency(total));
            calculateTotalFrontend();
        });
        
        // Calculate grand total (frontend)
        function calculateTotalFrontend() {
            var grandTotal = 0;
            
            $('.brb-item-row-frontend').each(function() {
                var quantity = parseFloat($(this).find('.brb-item-quantity').val()) || 0;
                var rate = parseFloat($(this).find('.brb-item-rate').val()) || 0;
                grandTotal += quantity * rate;
            });
            
            $('#brb-grand-total-frontend').text(brb_format_currency(grandTotal));
        }
        
        // Submit edit bill form
        $('#brb-edit-bill-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var $messages = $('#brb-form-messages');
            
            // Disable submit button
            $submitBtn.prop('disabled', true).text('Saving...');
            $messages.html('');
            
            // Collect form data
            var formData = new FormData($form[0]);
            formData.append('action', 'brb_update_bill');
            formData.append('nonce', $form.find('[name="brb_edit_bill_nonce"]').val());
            
            $.ajax({
                url: brbData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $messages.html('<div class="brb-success-message">' + response.data.message + '</div>');
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    } else {
                        $messages.html('<div class="brb-error-message">' + response.data.message + '</div>');
                        $submitBtn.prop('disabled', false).text('Save Changes');
                    }
                },
                error: function() {
                    $messages.html('<div class="brb-error-message">An error occurred. Please try again.</div>');
                    $submitBtn.prop('disabled', false).text('Save Changes');
                }
            });
        });
        
        // Submit create bill form
        $('#brb-create-bill-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var $messages = $('#brb-form-messages');
            
            // Disable submit button
            $submitBtn.prop('disabled', true).text('Creating...');
            $messages.html('');
            
            // Collect form data
            var formData = new FormData($form[0]);
            formData.append('action', 'brb_create_bill');
            formData.append('nonce', $form.find('[name="brb_create_bill_nonce"]').val());
            
            $.ajax({
                url: brbData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $messages.html('<div class="brb-success-message">' + response.data.message + '</div>');
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    } else {
                        $messages.html('<div class="brb-error-message">' + response.data.message + '</div>');
                        $submitBtn.prop('disabled', false).text('Create Bill');
                    }
                },
                error: function() {
                    $messages.html('<div class="brb-error-message">An error occurred. Please try again.</div>');
                    $submitBtn.prop('disabled', false).text('Create Bill');
                }
            });
        });
        
        // Update payment (AJAX)
        $(document).on('click', '.brb-update-payment-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var billId = $btn.data('bill-id');
            var paidAmount = parseFloat($('#brb-payment-input-' + billId).val()) || 0;
            
            $btn.prop('disabled', true).text('Updating...');
            
            $.ajax({
                url: brbData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'brb_update_payment',
                    nonce: brbData.nonce,
                    bill_id: billId,
                    paid_amount: paidAmount
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                        $btn.prop('disabled', false).text('Update Payment');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $btn.prop('disabled', false).text('Update Payment');
                }
            });
        });
        
        // Format currency helper
        function brb_format_currency(amount) {
            var currencySymbol = 'AED';
            var position = 'before';
            
            // Try to get from localized data if available
            if (typeof brbData !== 'undefined' && brbData.currencySymbol) {
                currencySymbol = brbData.currencySymbol;
                position = brbData.currencyPosition || 'before';
            }
            
            var formatted = parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            
            if (position === 'before') {
                return currencySymbol + ' ' + formatted;
            } else {
                return formatted + ' ' + currencySymbol;
            }
        }
        
        // Initial calculation
        if ($('#brb-grand-total-frontend').length) {
            calculateTotalFrontend();
        }
        
        // Update active nav link based on current URL
        var currentPath = window.location.pathname;
        $('.brb-nav-link').removeClass('active');
        
        if (currentPath.indexOf('/billing-dashboard/customers') !== -1) {
            $('.brb-nav-link[href*="/billing-dashboard/customers"]').addClass('active');
        } else if (currentPath.indexOf('/billing-dashboard/create') !== -1) {
            $('.brb-nav-link[href*="/billing-dashboard/create"]').addClass('active');
        } else if (currentPath.indexOf('/billing-dashboard/settings') !== -1) {
            $('.brb-nav-link[href*="/billing-dashboard/settings"]').addClass('active');
        } else if (currentPath.indexOf('/billing-dashboard/bill/') !== -1) {
            // Bill view page - no active nav
        } else if (currentPath.indexOf('/billing-dashboard') !== -1) {
            $('.brb-nav-link[href*="/billing-dashboard"]').first().addClass('active');
        }
        
        // Search and filter functionality
        var searchTimeout;
        var originalBills = $('#brb-bills-tbody').html();
        
        function filterBills() {
            var searchTerm = $('#brb-search-bills').val().toLowerCase();
            var statusFilter = $('#brb-filter-status').val();
            var $rows = $('.brb-bill-row');
            var visibleCount = 0;
            
            $rows.each(function() {
                var $row = $(this);
                var billNumber = $row.data('bill-number') || '';
                var status = $row.data('status') || '';
                var total = $row.data('total') || '';
                var customerName = $row.data('customer-name') || '';
                var customerEmail = $row.data('customer-email') || '';
                var customerPhone = $row.data('customer-phone') || '';
                var rowText = $row.text().toLowerCase();
                
                var matchesSearch = !searchTerm || 
                    billNumber.indexOf(searchTerm) !== -1 || 
                    rowText.indexOf(searchTerm) !== -1 ||
                    total.toString().indexOf(searchTerm) !== -1 ||
                    customerName.indexOf(searchTerm) !== -1 ||
                    customerEmail.indexOf(searchTerm) !== -1 ||
                    customerPhone.indexOf(searchTerm) !== -1;
                
                var matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });
            
            // Show message if no results
            if (visibleCount === 0 && (searchTerm || statusFilter)) {
                if ($('#brb-no-results').length === 0) {
                    $('#brb-bills-tbody').append('<tr id="brb-no-results"><td colspan="8" style="text-align: center; padding: 40px;"><strong>No bills found matching your criteria.</strong></td></tr>');
                }
            } else {
                $('#brb-no-results').remove();
            }
        }
        
        // Search input
        $('#brb-search-bills').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterBills, 300);
        });
        
        // Status filter
        $('#brb-filter-status').on('change', function() {
            filterBills();
        });
        
        // Reset filters
        $('#brb-reset-filters').on('click', function() {
            $('#brb-search-bills').val('');
            $('#brb-filter-status').val('');
            filterBills();
        });
        
        // ===== CUSTOMER SEARCH FUNCTIONALITY =====
        function initCustomerSearch() {
            var $searchInput = $('#brb_customer_search');
            var $hiddenInput = $('#brb_customer_id');
            var $dropdown = $('#brb-customer-dropdown');
            var customers = typeof brbCustomersData !== 'undefined' ? brbCustomersData : [];
            var selectedCustomerId = $hiddenInput.val();
            
            // Set initial display if customer is preselected
            if (selectedCustomerId && customers.length > 0) {
                var selected = customers.find(function(c) { return c.id == selectedCustomerId; });
                if (selected) {
                    $searchInput.val(selected.display);
                }
            }
            
            $searchInput.on('input', function() {
                var searchTerm = $(this).val().toLowerCase();
                
                if (searchTerm.length < 1) {
                    $dropdown.hide().empty();
                    $hiddenInput.val('');
                    return;
                }
                
                // Filter customers
                var matches = customers.filter(function(customer) {
                    return customer.name.toLowerCase().indexOf(searchTerm) !== -1 ||
                           customer.email.toLowerCase().indexOf(searchTerm) !== -1 ||
                           (customer.phone && customer.phone.toLowerCase().indexOf(searchTerm) !== -1);
                });
                
                // Display results
                if (matches.length > 0) {
                    var html = '<ul>';
                    matches.forEach(function(customer) {
                        html += '<li data-id="' + customer.id + '">';
                        html += '<strong>' + customer.name + '</strong>';
                        html += '<span class="brb-customer-email">' + customer.email + '</span>';
                        if (customer.phone) {
                            html += '<span class="brb-customer-phone">' + customer.phone + '</span>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                    $dropdown.html(html).show();
                } else {
                    $dropdown.html('<ul><li class="brb-no-results">No customers found</li></ul>').show();
                }
            });
            
            // Handle selection
            $(document).on('click', '#brb-customer-dropdown li[data-id]', function() {
                var customerId = $(this).data('id');
                var customer = customers.find(function(c) { return c.id == customerId; });
                
                if (customer) {
                    $searchInput.val(customer.display);
                    $hiddenInput.val(customer.id);
                    $dropdown.hide();
                }
            });
            
            // Hide dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.brb-customer-search-wrapper').length) {
                    $dropdown.hide();
                }
            });
            
            // Handle keyboard navigation
            $searchInput.on('keydown', function(e) {
                var $items = $dropdown.find('li[data-id]');
                var $selected = $items.filter('.brb-selected');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if ($selected.length) {
                        $selected.removeClass('brb-selected').next().addClass('brb-selected');
                    } else {
                        $items.first().addClass('brb-selected');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if ($selected.length) {
                        $selected.removeClass('brb-selected').prev().addClass('brb-selected');
                    } else {
                        $items.last().addClass('brb-selected');
                    }
                } else if (e.key === 'Enter' && $selected.length) {
                    e.preventDefault();
                    $selected.click();
                } else if (e.key === 'Escape') {
                    $dropdown.hide();
                }
            });
        }
        
        // Initialize customer search if field exists
        if ($('#brb_customer_search').length) {
            initCustomerSearch();
        }
        
        // ===== RETURN ITEMS FUNCTIONALITY (FRONTEND) =====
        var returnIndexFrontend = $('.brb-return-row-frontend').length;
        
        // Add new return item (frontend)
        $(document).on('click', '.brb-add-return-frontend', function(e) {
            e.preventDefault();
            
            var newRow = $('<tr class="brb-return-row-frontend" data-index="' + returnIndexFrontend + '">' +
                '<td><input type="text" class="brb-return-description-frontend" placeholder="Return item description" /></td>' +
                '<td><input type="number" class="brb-return-quantity-frontend" step="0.01" min="0" value="1" /></td>' +
                '<td><input type="number" class="brb-return-rate-frontend" step="0.01" min="0" /></td>' +
                '<td><span class="brb-return-total-frontend">' + brb_format_currency(0) + '</span></td>' +
                '<td><button type="button" class="brb-icon-btn brb-icon-btn-remove brb-remove-return-frontend" title="Remove Return Item">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<polyline points="3 6 5 6 21 6"></polyline>' +
                '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>' +
                '</svg></button></td>' +
                '</tr>');
            
            $('#brb-returns-tbody-frontend').append(newRow);
            returnIndexFrontend++;
        });
        
        // Remove return item (frontend)
        $(document).on('click', '.brb-remove-return-frontend', function(e) {
            e.preventDefault();
            $(this).closest('.brb-return-row-frontend').remove();
            calculateReturnTotalFrontend();
        });
        
        // Calculate return item total (frontend)
        $(document).on('input', '.brb-return-quantity-frontend, .brb-return-rate-frontend', function() {
            var row = $(this).closest('.brb-return-row-frontend');
            var quantity = parseFloat(row.find('.brb-return-quantity-frontend').val()) || 0;
            var rate = parseFloat(row.find('.brb-return-rate-frontend').val()) || 0;
            var total = quantity * rate;
            
            row.find('.brb-return-total-frontend').text(brb_format_currency(total));
            calculateReturnTotalFrontend();
        });
        
        // Calculate return grand total (frontend)
        function calculateReturnTotalFrontend() {
            var returnTotal = 0;
            
            $('.brb-return-row-frontend').each(function() {
                var quantity = parseFloat($(this).find('.brb-return-quantity-frontend').val()) || 0;
                var rate = parseFloat($(this).find('.brb-return-rate-frontend').val()) || 0;
                returnTotal += quantity * rate;
            });
            
            $('#brb-return-grand-total-frontend').text(brb_format_currency(returnTotal));
            
            // Update payment summary if on edit page
            if ($('#brb-edit-bill-form').length) {
                updatePaymentSummary();
            }
        }
        
        // Update payment summary on edit page
        function updatePaymentSummary() {
            var originalTotal = parseFloat($('#brb-grand-total-frontend').text().replace(/[^\d.-]/g, '')) || 0;
            var returnTotal = 0;
            
            $('.brb-return-row-frontend').each(function() {
                var quantity = parseFloat($(this).find('.brb-return-quantity-frontend').val()) || 0;
                var rate = parseFloat($(this).find('.brb-return-rate-frontend').val()) || 0;
                returnTotal += quantity * rate;
            });
            
            var adjustedTotal = Math.max(0, originalTotal - returnTotal);
            var paidAmount = parseFloat($('#brb_paid_amount').val()) || 0;
            var pending = Math.max(0, adjustedTotal - paidAmount);
            var refundDue = Math.max(0, paidAmount - adjustedTotal);
            
            $('#brb-original-total-display').text(brb_format_currency(originalTotal));
            $('#brb-return-total-display').text('-' + brb_format_currency(returnTotal));
            $('#brb-adjusted-total-display').text(brb_format_currency(adjustedTotal));
            
            // Show pending or refund based on which applies
            if (refundDue > 0) {
                // Customer overpaid - show refund due
                $('#brb-pending-row').hide();
                $('#brb-refund-row').show();
                $('#brb-refund-display').text(brb_format_currency(refundDue));
            } else {
                // Normal case - show pending amount
                $('#brb-pending-row').show();
                $('#brb-refund-row').hide();
                $('#brb-pending-display').text(brb_format_currency(pending));
            }
        }
        
        // Update payment summary when items or paid amount changes
        $(document).on('input', '.brb-item-quantity, .brb-item-rate, .brb-return-quantity-frontend, .brb-return-rate-frontend, #brb_paid_amount', function() {
            if ($('#brb-edit-bill-form').length) {
                // Recalculate grand total first
                calculateTotalFrontend();
                // Then update payment summary
                setTimeout(updatePaymentSummary, 100);
            }
        });
        
        // Save return items (frontend)
        $(document).on('click', '.brb-save-returns', function(e) {
            e.preventDefault();
            
            var billId = $(this).data('bill-id');
            var returnItems = [];
            
            $('.brb-return-row-frontend').each(function() {
                var description = $(this).find('.brb-return-description-frontend').val();
                if (description && description.trim() !== '') {
                    returnItems.push({
                        description: description.trim(),
                        quantity: parseFloat($(this).find('.brb-return-quantity-frontend').val()) || 0,
                        rate: parseFloat($(this).find('.brb-return-rate-frontend').val()) || 0
                    });
                }
            });
            
            var $button = $(this);
            var originalText = $button.text();
            $button.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: brbData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'brb_save_returns',
                    nonce: brbData.saveReturnsNonce || brbData.nonce,
                    bill_id: billId,
                    return_items: returnItems
                },
                success: function(response) {
                    if (response.success) {
                        $('#brb-return-messages').html(
                            '<div class="brb-success-message">' + response.data.message + '</div>'
                        );
                        
                        // Reload page after 1.5 seconds to show updated totals
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        $('#brb-return-messages').html(
                            '<div class="brb-error-message">' + response.data.message + '</div>'
                        );
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    $('#brb-return-messages').html(
                        '<div class="brb-error-message">An error occurred. Please try again.</div>'
                    );
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Initial return calculation (frontend)
        calculateReturnTotalFrontend();
        
        // Customer form submission
        $('#brb-add-customer-form, #brb-edit-customer-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Saving...');
            
            const formData = new FormData(this);
            formData.append('action', 'brb_save_customer');
            formData.append('nonce', $('#brb_customer_nonce').val());
            
            $.ajax({
                url: (typeof brbData !== 'undefined' && brbData.ajaxUrl) ? brbData.ajaxUrl : '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            alert(response.data.message);
                            $button.prop('disabled', false).text(originalText);
                        }
                    } else {
                        alert(response.data.message || 'An error occurred. Please try again.');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    });
    
})(jQuery);

