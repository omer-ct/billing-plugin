(function($) {
    'use strict';
    
    $(document).ready(function() {
        var itemIndex = $('.brb-item-row').length;
        
        // Add new item
        $(document).on('click', '.brb-add-item', function(e) {
            e.preventDefault();
            
            var newRow = $('.brb-item-row').first().clone();
            newRow.attr('data-index', itemIndex);
            newRow.find('input').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[\d+\]/, '[' + itemIndex + ']');
                    $(this).attr('name', name);
                    $(this).val('');
                }
            });
            newRow.find('.brb-item-total').text(brb_format_currency(0));
            
            $('#brb-items-tbody').append(newRow);
            itemIndex++;
        });
        
        // Remove item
        $(document).on('click', '.brb-remove-item', function(e) {
            e.preventDefault();
            
            if ($('.brb-item-row').length > 1) {
                $(this).closest('.brb-item-row').remove();
                calculateTotal();
            } else {
                alert('At least one item is required.');
            }
        });
        
        // Calculate item total
        $(document).on('input', '.brb-item-quantity, .brb-item-rate', function() {
            var row = $(this).closest('.brb-item-row');
            var quantity = parseFloat(row.find('.brb-item-quantity').val()) || 0;
            var rate = parseFloat(row.find('.brb-item-rate').val()) || 0;
            var total = quantity * rate;
            
            row.find('.brb-item-total').text(brb_format_currency(total));
            calculateTotal();
        });
        
        // Calculate grand total
        function calculateTotal() {
            var grandTotal = 0;
            
            $('.brb-item-row').each(function() {
                var quantity = parseFloat($(this).find('.brb-item-quantity').val()) || 0;
                var rate = parseFloat($(this).find('.brb-item-rate').val()) || 0;
                grandTotal += quantity * rate;
            });
            
            $('#brb-grand-total').text(brb_format_currency(grandTotal));
            $('#brb-total-amount').val(grandTotal);
            $('#brb-payment-total').text(brb_format_currency(grandTotal));
            
            // Update pending amount
            updatePendingAmount();
        }
        
        // Update pending amount
        function updatePendingAmount() {
            var originalTotal = parseFloat($('#brb-total-amount').val()) || 0;
            var returnTotal = 0;
            
            $('.brb-return-row').each(function() {
                var quantity = parseFloat($(this).find('.brb-return-quantity').val()) || 0;
                var rate = parseFloat($(this).find('.brb-return-rate').val()) || 0;
                returnTotal += quantity * rate;
            });
            
            var adjustedTotal = Math.max(0, originalTotal - returnTotal);
            var paid = parseFloat($('#brb_paid_amount').val()) || 0;
            var pending = Math.max(0, adjustedTotal - paid);
            var refundDue = Math.max(0, paid - adjustedTotal);
            
            // Show pending or refund based on which applies
            if (refundDue > 0) {
                // Customer overpaid - show refund due
                if ($('#brb-payment-refund').length === 0) {
                    $('#brb-payment-pending').parent().after('<p><strong>Refund Due to Customer:</strong><br><span id="brb-payment-refund" style="color: #dc2626; font-weight: 700; font-size: 1.2em;"></span></p>');
                }
                $('#brb-payment-pending').parent().hide();
                $('#brb-payment-refund').text(brb_format_currency(refundDue)).parent().show();
            } else {
                // Normal case - show pending amount
                $('#brb-payment-refund').parent().hide();
                $('#brb-payment-pending').parent().show();
                
                var pendingElement = $('#brb-payment-pending');
                pendingElement.text(brb_format_currency(pending));
                
                if (pending > 0) {
                    pendingElement.removeClass('brb-paid-full').addClass('brb-pending-amount');
                } else {
                    pendingElement.removeClass('brb-pending-amount').addClass('brb-paid-full');
                }
            }
            
            // Update payment total display
            $('#brb-payment-total').text(brb_format_currency(adjustedTotal));
            
            // Remove max limit on paid amount to allow overpayment
            $('#brb_paid_amount').removeAttr('max');
        }
        
        // Update pending when paid amount changes
        $(document).on('input', '#brb_paid_amount', function() {
            updatePendingAmount();
        });
        
        // Format currency helper
        function brb_format_currency(amount) {
            var currencySymbol = brbAdminData.currencySymbol || 'AED';
            var position = brbAdminData.currencyPosition || 'before';
            var formatted = parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            
            if (position === 'before') {
                return currencySymbol + ' ' + formatted;
            } else {
                return formatted + ' ' + currencySymbol;
            }
        }
        
        // Initial calculation
        calculateTotal();
        
        // ===== RETURN ITEMS FUNCTIONALITY =====
        var returnIndex = $('.brb-return-row').length;
        
        // Add new return item
        $(document).on('click', '.brb-add-return', function(e) {
            e.preventDefault();
            
            var newRow = $('.brb-return-row').first().clone();
            if (newRow.length === 0) {
                // Create first row if none exists
                newRow = $('<tr class="brb-return-row" data-index="' + returnIndex + '">' +
                    '<td><input type="text" name="brb_return_items[' + returnIndex + '][description]" class="regular-text brb-return-description" placeholder="Return item description" /></td>' +
                    '<td><input type="number" name="brb_return_items[' + returnIndex + '][quantity]" class="small-text brb-return-quantity" step="0.01" min="0" /></td>' +
                    '<td><input type="number" name="brb_return_items[' + returnIndex + '][rate]" class="small-text brb-return-rate" step="0.01" min="0" /></td>' +
                    '<td><span class="brb-return-total">' + brb_format_currency(0) + '</span></td>' +
                    '<td><button type="button" class="brb-icon-btn brb-icon-btn-remove brb-remove-return" title="Remove Return Item">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<polyline points="3 6 5 6 21 6"></polyline>' +
                    '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>' +
                    '</svg></button></td>' +
                    '</tr>');
            } else {
                newRow.attr('data-index', returnIndex);
                newRow.find('input').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + returnIndex + ']');
                        $(this).attr('name', name);
                        $(this).val('');
                    }
                });
                newRow.find('.brb-return-total').text(brb_format_currency(0));
            }
            
            $('#brb-returns-tbody').append(newRow);
            returnIndex++;
        });
        
        // Remove return item
        $(document).on('click', '.brb-remove-return', function(e) {
            e.preventDefault();
            $(this).closest('.brb-return-row').remove();
            calculateReturnTotal();
            updateAdjustedTotal();
        });
        
        // Calculate return item total
        $(document).on('input', '.brb-return-quantity, .brb-return-rate', function() {
            var row = $(this).closest('.brb-return-row');
            var quantity = parseFloat(row.find('.brb-return-quantity').val()) || 0;
            var rate = parseFloat(row.find('.brb-return-rate').val()) || 0;
            var total = quantity * rate;
            
            row.find('.brb-return-total').text(brb_format_currency(total));
            calculateReturnTotal();
            updateAdjustedTotal();
        });
        
        // Calculate return grand total
        function calculateReturnTotal() {
            var returnTotal = 0;
            
            $('.brb-return-row').each(function() {
                var quantity = parseFloat($(this).find('.brb-return-quantity').val()) || 0;
                var rate = parseFloat($(this).find('.brb-return-rate').val()) || 0;
                returnTotal += quantity * rate;
            });
            
            $('#brb-return-grand-total').text(brb_format_currency(returnTotal));
        }
        
        // Update adjusted total (original - returns)
        function updateAdjustedTotal() {
            var originalTotal = parseFloat($('#brb-total-amount').val()) || 0;
            var returnTotal = 0;
            
            $('.brb-return-row').each(function() {
                var quantity = parseFloat($(this).find('.brb-return-quantity').val()) || 0;
                var rate = parseFloat($(this).find('.brb-return-rate').val()) || 0;
                returnTotal += quantity * rate;
            });
            
            var adjustedTotal = Math.max(0, originalTotal - returnTotal);
            $('#brb-payment-total').text(brb_format_currency(adjustedTotal));
            
            // Update pending amount
            updatePendingAmountWithReturns(adjustedTotal);
        }
        
        // Update pending amount with returns
        function updatePendingAmountWithReturns(adjustedTotal) {
            var paid = parseFloat($('#brb_paid_amount').val()) || 0;
            var pending = Math.max(0, adjustedTotal - paid);
            
            var pendingElement = $('#brb-payment-pending');
            pendingElement.text(brb_format_currency(pending));
            
            if (pending > 0) {
                pendingElement.removeClass('brb-paid-full').addClass('brb-pending-amount');
            } else {
                pendingElement.removeClass('brb-pending-amount').addClass('brb-paid-full');
            }
            
            // Update max attribute for paid amount
            $('#brb_paid_amount').attr('max', adjustedTotal);
        }
        
        // Update pending when paid amount changes (with returns)
        $(document).on('input', '#brb_paid_amount', function() {
            var originalTotal = parseFloat($('#brb-total-amount').val()) || 0;
            var returnTotal = 0;
            
            $('.brb-return-row').each(function() {
                var quantity = parseFloat($(this).find('.brb-return-quantity').val()) || 0;
                var rate = parseFloat($(this).find('.brb-return-rate').val()) || 0;
                returnTotal += quantity * rate;
            });
            
            var adjustedTotal = Math.max(0, originalTotal - returnTotal);
            updatePendingAmountWithReturns(adjustedTotal);
        });
        
        // Initial return calculation
        calculateReturnTotal();
        updateAdjustedTotal();
    });
    
})(jQuery);

