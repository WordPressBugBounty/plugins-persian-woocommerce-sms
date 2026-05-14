jQuery(function ($) {
    /**
     * Inject shortcode buttons and handle insertion
     * @param {jQuery} form_element - Target form element
     * @param {Object} shortcodes - {shortcode: label}
     */
    function pwsms_setup_shortcodes(form_element, shortcodes) {
        if (!form_element.length || typeof shortcodes !== 'object') return;

        form_element.find('tr').each(function () {
            const table_row = $(this);
            const excluded_inputs = [
                'sms_notif_settings[notif_options]',
                'sms_super_admin_settings[admin_low_stock]',
                'sms_super_admin_settings[admin_out_stock]',
                'sms_super_admin_settings[super_admin_bots]'
            ];

            // Matches:
            // name="sms_buyer_settings[product_admin_sms_body_xyz]"
            // name="sms_notif_settings[notif_no_stock_sms]"
            const textarea = table_row.find('textarea').filter(function () {

                if (excluded_inputs.indexOf(this.name) > -1) {
                    return false;
                }

                return /^sms_.*settings/.test(this.name);
            });

            if (!textarea.length) return;

            pwsms_setup_textarea_shortcodes(textarea, shortcodes);
        });

        form_element.on('click', '.pwsms-shortcode-insert-button', function () {
            const current_button = $(this);
            const current_table_row = current_button.closest('tr');
            const current_textarea = current_table_row.find('textarea').filter(function () {
                return /^sms_.*settings/.test(this.name);
            });

            if (!current_textarea.length) return;

            const textarea = current_textarea.get(0); // native DOM element
            const shortcode = current_button.data('shortcode');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const value = current_textarea.val();

            current_textarea.val(value.substring(0, start) + shortcode + value.substring(end));
            textarea.focus();
            textarea.setSelectionRange(start + shortcode.length, start + shortcode.length);
        });

    }


    function pwsms_setup_textarea_shortcodes(textarea, shortcodes) {
        let toolbar = textarea.next('.shortcode-toolbar');

        if (toolbar.length === 0) {
            toolbar = $('<div class="shortcode-toolbar" style="margin: 8px 0;"></div>');
        }

        $.each(shortcodes, function (shortcode, label) {
            const button = $('<button type="button" class="pwsms-shortcode-insert-button" style="margin:2px;"></button>');
            button.text(label);
            button.attr('data-shortcode', shortcode);
            toolbar.append(button);
        });

        textarea.after(toolbar);
    }

    /**
     * Set specific shortcodes for specific inputs
     * */
    function pwsms_setup_input_shortcodes(selectors, shortcodes_group) {

        $(selectors.join(', ')).each(function () {

            if (!$(this).length) {
                return;
            }

            pwsms_setup_textarea_shortcodes($(this), shortcodes_group);

        });

    }

    /**
     * ###### Setup Forms #########
     * */

    // Target specific form if it exists
    const pwsms_general_form = $('[id^="sms_"][id$="_settings"]');
    const pwsms_notification_form = $('#sms_notif_settings');

    // pwsms_shortcodes come from backend
    let pwsms_final_shortcodes = pwsms_shortcodes.core;

    if (pwsms_notification_form.length) {
        pwsms_final_shortcodes = pwsms_shortcodes.notification
    }

    pwsms_setup_shortcodes(pwsms_general_form, pwsms_final_shortcodes);

    pwsms_setup_input_shortcodes(
        [
            '#sms_super_admin_settings\\[admin_out_stock\\]',
            '#sms_super_admin_settings\\[admin_low_stock\\]'
        ], pwsms_shortcodes.stock
    );

    pwsms_setup_input_shortcodes(
        [
            '#sms_super_admin_settings\\[super_admin_sms_body_set-post-tracking-code\\]',
            '#sms_buyer_settings\\[sms_body_set-post-tracking-code\\]',
            '#sms_product_admin_settings\\[product_admin_sms_body_set-post-tracking-code\\]'
        ], pwsms_shortcodes.post_tracking
    )

});
