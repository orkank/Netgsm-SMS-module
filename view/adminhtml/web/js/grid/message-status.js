define([
  'jquery',
  'Magento_Ui/js/modal/modal',
  'mage/translate'
], function ($, modal, $t) {
  'use strict';

  return function (config) {
      $(document).on('click', '.idangerous-message-status', function (e) {
          e.preventDefault();
          var messageId = $(this).data('message-id');

          // Define the popup container before opening the modal
          var contentContainer = $('<div/>', { class: 'content-container' });

          var options = {
              type: 'popup',
              responsive: true,
              title: $t('Message Status'),
              buttons: [{
                  text: $t('Close'),
                  class: 'action-secondary',
                  click: function () {
                      this.closeModal();
                  }
              }]
          };

          var popup = contentContainer.modal(options);

          $.ajax({
              url: config.statusUrl,
              data: {
                  message_id: messageId,
                  form_key: window.FORM_KEY
              },
              type: 'GET',
              dataType: 'json',
              showLoader: true,
              success: function (response) {
                  if (response.success) {
                      var html = '<table class="admin__table-secondary">';
                      html += '<tr><th>' + $t('Phone') + '</th><td>' + response.status.phone + '</td></tr>';
                      html += '<tr><th>' + $t('Status') + '</th><td>' + response.status.status + '</td></tr>';
                      html += '<tr><th>' + $t('Operator') + '</th><td>' + response.status.operator + '</td></tr>';
                      html += '<tr><th>' + $t('Message Count') + '</th><td>' + response.status.message_count + '</td></tr>';
                      html += '<tr><th>' + $t('Delivery Date') + '</th><td>' + response.status.delivery_date + '</td></tr>';
                      html += '<tr><th>' + $t('Job ID') + '</th><td>' + response.status.job_id + '</td></tr>';
                      if (response.status.error_code) {
                          html += '<tr><th>' + $t('Error') + '</th><td>' + response.status.error_code + '</td></tr>';
                      }
                      html += '</table>';

                      // Add content before opening modal
                      contentContainer.html(html);
                      popup.modal('openModal');
                  } else {
                      contentContainer.html(
                          '<div class="message message-error error">' + response.message + '</div>'
                      );
                      popup.modal('openModal');
                  }
              }
          });
      });
  };
});
