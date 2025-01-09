define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    return function (config) {
        function updateRecipientCount() {
            var form = $('#bulk-sms-form');
            var countElement = $('#recipient-count');
            var submitButton = $('#submit-button');
            var formKey = $('input[name="form_key"]').val();
            var customerType = form.find('[name="customer_type"]').val();

            var data = {
                form_key: formKey,
                filters: {
                    customer_type: customerType,
                    customer_groups: customerType === 'guest' ? [] : form.find('[name="customer_groups[]"]').val(),
                    order_period: customerType === 'guest' ? '' : form.find('[name="order_period"]').val(),
                    min_purchase_count: customerType === 'guest' ? '' : form.find('[name="min_purchase_count"]').val()
                }
            };

            $.ajax({
                url: config.recipientCountUrl,
                data: data,
                type: 'POST',
                dataType: 'json',
                beforeSend: function() {
                    countElement.text('Calculating...');
                    submitButton.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        countElement.text(response.count + ' recipients');
                        submitButton.prop('disabled', response.count === 0);
                    } else {
                        countElement.text('Error: ' + response.message);
                        submitButton.prop('disabled', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', status, error);
                    countElement.text('Error calculating recipients');
                    submitButton.prop('disabled', true);
                }
            });
        }

        function toggleFilters(isGuest) {
            var form = $('#bulk-sms-form');
            var customerGroups = form.find('[name="customer_groups[]"]');
            var orderPeriod = form.find('[name="order_period"]');
            var minPurchaseCount = form.find('[name="min_purchase_count"]');

            if (isGuest) {
                customerGroups.prop('disabled', true).val('');
                orderPeriod.prop('disabled', true).val('');
                minPurchaseCount.prop('disabled', true).val('');

                // Optional: Add visual indication
                customerGroups.closest('.field').addClass('disabled');
                orderPeriod.closest('.field').addClass('disabled');
                minPurchaseCount.closest('.field').addClass('disabled');
            } else {
                customerGroups.prop('disabled', false);
                orderPeriod.prop('disabled', false);
                minPurchaseCount.prop('disabled', false);

                // Optional: Remove visual indication
                customerGroups.closest('.field').removeClass('disabled');
                orderPeriod.closest('.field').removeClass('disabled');
                minPurchaseCount.closest('.field').removeClass('disabled');
            }
        }

        function updateMessageCounter() {
            var messageField = $('#message');
            var counterElement = $('#message-counter');
            var text = messageField.val();

            // Check if text contains any non-GSM characters
            var isUnicode = /[^\u0000-\u007F\u00A0-\u00FF]/.test(text);

            // Set limits based on encoding
            var charLimit = isUnicode ? 70 : 160;
            var charsPerMessage = isUnicode ? 67 : 153;

            var length = text.length;
            var messageCount = Math.ceil(length / charsPerMessage) || 1;
            var remainingChars = (messageCount * charsPerMessage) - length;

            // Update counter display
            counterElement.html(
                'Characters: ' + length +
                ' | Remaining: ' + remainingChars +
                ' | Messages: ' + messageCount +
                (isUnicode ? ' (Unicode)' : ' (GSM)')
            );

            // Optional: Add visual indication
            if (length > 0) {
                counterElement.removeClass('empty');
                if (isUnicode) {
                    counterElement.addClass('unicode');
                } else {
                    counterElement.removeClass('unicode');
                }
            } else {
                counterElement.addClass('empty');
            }
        }

        $(document).ready(function() {
            var form = $('#bulk-sms-form');
            var filterInputs = form.find('select, input[type="checkbox"], input[type="number"]');
            var customerTypeSelect = form.find('[name="customer_type"]');

            // Initial state
            toggleFilters(customerTypeSelect.val() === 'guest');

            // Handle customer type changes
            customerTypeSelect.on('change', function() {
                toggleFilters($(this).val() === 'guest');
                updateRecipientCount();
            });

            // Handle other filter changes
            filterInputs.not('[name="customer_type"]').on('change', function() {
                updateRecipientCount();
            });

            updateRecipientCount();

            // Add message counter element after message field
            var messageField = $('#message');
            if (messageField.length) {
                $('<div id="message-counter" class="message-counter empty"></div>')
                    .insertAfter(messageField);

                // Initial count
                updateMessageCounter();

                // Update on input
                messageField.on('input', updateMessageCounter);
            }
        });
    };
});