/**
 * WGM Email Settings — Dynamic email list management.
 */
(function ($) {
    'use strict';

    var EmailSettings = {
        container: null,
        template: null,

        init: function () {
            this.container = $('#wgm-email-list');
            if (!this.container.length) {
                return;
            }
            this.template = this.container.find('.wgm-email-row-template');
            this.bindEvents();
            this.updateRemoveButtons();
        },

        bindEvents: function () {
            var self = this;

            this.container.on('click', '.wgm-add-email', function (e) {
                e.preventDefault();
                self.addRow();
            });

            this.container.on('click', '.wgm-remove-email', function (e) {
                e.preventDefault();
                $(this).closest('.wgm-email-row').remove();
                self.updateRemoveButtons();
            });
        },

        addRow: function () {
            var row = this.template.clone(true);
            row.removeClass('wgm-email-row-template')
                .css('display', '')
                .find('input[type="email"]')
                .val('')
                .prop('disabled', false);
            this.template.before(row);
            this.updateRemoveButtons();
            row.find('input[type="email"]').focus();
        },

        updateRemoveButtons: function () {
            var rows = this.container.find('.wgm-email-row:not(.wgm-email-row-template)');
            rows.find('.wgm-remove-email').toggle(rows.length > 1);
        }
    };

    $(document).ready(function () {
        EmailSettings.init();
    });

})(jQuery);
