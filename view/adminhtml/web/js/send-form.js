define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (config, element) {
        var form = $(element);

        // Remove any existing submit handlers
        form.off('submit');

        form.on('submit', function (e) {
            if (!$(this).valid()) {
                return false;
            }

            // Allow the form to submit normally
            return true;
        });
    };
});