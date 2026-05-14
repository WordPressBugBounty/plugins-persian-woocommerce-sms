jQuery(function ($) {

    $('#pwoosms-send-sms-bulk-form').on('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const mobile = $('#pwoosms_mobile').val().trim();
        const message = $('#pwoosms_message').val().trim();

        if (!mobile || !message) {
            Swal.fire({
                icon: 'warning',
                title: 'ورودی نامعتبر',
                text: 'لطفاً شماره موبایل و متن پیامک را وارد کنید.',
                confirmButtonText: 'متوجه شدم',
            });
            return;
        }

        Swal.fire({
            title: 'در حال ارسال پیامک...',
            text: 'لطفاً منتظر بمانید.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch(pwsms_bulk_sms.rest_url + 'pwsms/sms/send', {
            method: 'POST', headers: {
                'Content-Type': 'application/json', 'X-WP-Nonce': pwsms_bulk_sms.nonce,
            }, body: JSON.stringify({mobile, message}),
        })
            .then(async response => {
                let json;
                try {
                    json = await response.json();
                } catch (e) {
                    json = {message: 'پاسخی از سرور دریافت نشد یا پاسخ معتبر نبود.'};
                }

                if (!response.ok) {
                    const err = new Error(json.message || `HTTP ${response.status}`);
                    err.error = json.error || null;
                    err.status = response.status;
                    throw err;
                }

                return json;
            })
            .then(response => {
                Swal.close();

                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'ارسال موفق',
                        html: `<p>${response.message}</p>${response.details ? `<p><strong>${response.details}</strong></p>` : ''}`,
                        confirmButtonText: 'باشه',
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا در ارسال',
                        text: response.error || response.message || 'پیامک ارسال نشد.',
                        confirmButtonText: 'متوجه شدم',
                    });
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: error.message,
                    text: error.error || 'ارسال پیامک با خطا مواجه شد.',
                    confirmButtonText: 'متوجه شدم',
                });
            });

    });

});
