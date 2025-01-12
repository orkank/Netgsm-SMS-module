define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (config, element) {
        var form = $(element);
        var messageField = form.find('#message');
        var isOtpField = form.find('#is_otp');

        // Remove any existing submit handlers
        form.off('submit');

        // Add OTP message length validation
        isOtpField.on('change', function() {
            if ($(this).is(':checked')) {
                messageField.attr('maxlength', '160');
                if (messageField.val().length > 160) {
                    messageField.val(messageField.val().substring(0, 160));
                }
            } else {
                messageField.removeAttr('maxlength');
            }
        });

        form.on('submit', function (e) {
            if (!$(this).valid()) {
                return false;
            }

            // Additional OTP validation
            if (isOtpField.is(':checked') && messageField.val().length > 160) {
                alert($t('OTP messages must be less than 160 characters.'));
                return false;
            }

            // Allow the form to submit normally
            return true;
        });
    };
});