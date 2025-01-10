define([
    'Magento_Ui/js/grid/columns/actions',
    'jquery',
    'mage/translate'
], function (Actions, $, $t) {
    'use strict';

    return Actions.extend({
        defaultCallback: function (actionData, action, row) {
            if (action.confirm) {
                this.confirm(action, row);
                return;
            }
            this._super();
        },

        confirm: function (action, row) {
            var self = this;
            $('<div/>').confirm({
                title: action.confirm.title,
                content: action.confirm.message,
                actions: {
                    confirm: function () {
                        self.processAction(action, row);
                    }
                }
            });
        },

        processAction: function (action, row) {
            $.ajax({
                url: action.href,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY
                },
                showLoader: true,
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                }
            });
        }
    });
});