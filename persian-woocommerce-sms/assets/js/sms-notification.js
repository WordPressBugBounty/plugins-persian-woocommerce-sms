jQuery(document).ready(function ($) {

    $(".sms-notif-content").hide();

    $(document.body)

        .on("change", ".sms-notif-enable", function () {

            if ($(this).is(":checked")) {
                $(this).closest("form").find(".sms-notif-content").fadeIn();
            } else {
                $(this).closest("form").find(".sms-notif-content").fadeOut();
            }

        })
        // User subscription in notification groups
        .on("click", ".sms-notif-submit", function () {

            let form = $(this).closest("form");
            let result = form.find(".sms-notif-result");

            result.html('<img style="width:16px;display:inline;" src="' + pwsms_notification.loader + '" />');

            let sms_group = [];
            form.find(".sms-notif-groups:checked").each(function (i) {
                sms_group[i] = $(this).val();
            });

            let current_product_id = pwsms_notification.product_id;

            let product_form = $('form:has(input[name="product_id"][value="' + pwsms_notification.product_id + '"])');
            let variation_id_input = product_form.find("input.variation_id");

            if (variation_id_input.length) {

                let variation_id = parseInt(variation_id_input.val(), 10);

                if (!isNaN(variation_id) && variation_id > 0) {
                    current_product_id = variation_id;
                }
            }

            fetch(pwsms_notification.rest_url + 'pwsms/notification/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': pwsms_notification.nonce
                },
                body: JSON.stringify({
                    sms_mobile: form.find(".sms-notif-mobile").val(),
                    sms_group: sms_group,
                    product_id: current_product_id
                })
            })
                .then(response => {

                    if (!response.ok) {
                        throw new Error(`خطایی در شبکه رخ داده است. وضعیت: ${response.status}`);
                    }

                    return response.json();
                })
                .then(data => {
                    const tick_icon = pwsms_notification.loader.replace('ajax-loader.gif', 'tick.png');
                    const false_icon = pwsms_notification.loader.replace('ajax-loader.gif', 'false.png');
                    const icon_src = data.success ? tick_icon : false_icon;

                    result.html(`<img style="width:16px;display:inline;" src="${icon_src}">&nbsp;${data.message}`);
                })
                .catch(error => {
                    console.error('خطای افزودن مشترک: ', error);
                    const falseIcon = pwsms_notification.loader.replace('ajax-loader.gif', 'false.png');
                    result.html(`<img style="width:16px;display:inline;" src="${falseIcon}">&nbsp;خطایی رخ داده است. مجددا تلاش کنید.`);
                });

            return false;
        })
        // Update groups based on selected variation in variable products
        .on('found_variation', 'form.variations_form', function (event, variation) {

            const variation_id = variation.variation_id;
            const groups_container_el = $('.sms-notif-content');
            const groups_toggle_el = $('.sms-notif-enable-p');

            if (!variation_id || groups_container_el.length === 0) {
                return;
            }

            groups_toggle_el.css('opacity', '0.5');

            fetch(pwsms_notification.rest_url + 'pwsms/notification/groups', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': pwsms_notification.nonce,
                },
                body: JSON.stringify({
                    product_id: variation_id
                })
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`خطایی در شبکه رخ داده است. وضعیت: ${response.status}`);
                    }
                    return response.json();
                })
                .then(groups => {
                    groups_container_el.find('.sms-notif-groups-label').remove();
                    groups_container_el.find('br').remove();

                    if (!groups || groups.length === 0) {
                        groups_container_el.hide();
                        return;
                    }

                    let groups_element = '';
                    groups.forEach(function (group) {
                        groups_element += `
                                        <label class="sms-notif-groups-label sms-notif-groups-label-${group.code}">
                                            <input type="checkbox" class="sms-notif-groups" name="sms_notif_groups[]" value="${group.code}"/>
                                            ${group.text}
                                        </label><br>
                                        `;
                    });

                    groups_container_el.prepend(groups_element);
                })
                .catch(error => {
                    console.error('دریافت گروه های خبرنامه پیامکی محصول متغیر با مشکل مواجه شد:', error.message || error);
                })
                .finally(() => {
                    groups_toggle_el.css('opacity', '1')
                });
        })
});
