jQuery(document).ready(function ($) {

    $("#doaction").on("click", function (e) {
        const action = $("#bulk-action-selector-top").val();

        // Nested if situation in case if there's another custom action would be processed separately
        if (action === "custom_sms") {
            e.preventDefault();

            const selectedIds = $('input[name="bulk_action_ids[]"]:checked')
                .map(function () {
                    return $(this).attr("data-phone");
                })
                .get();

            const mobilesArray = selectedIds.filter(
                (id) => id && id.trim() !== ""
            );
            const mobiles = mobilesArray.join(",");
            const total = selectedIds.length;
            const count = mobilesArray.length;

            if (!total || !count) {
                Swal.fire({
                    icon: "error",
                    title: "خطا",
                    text: "لطفاً حداقل یک کاربر با شماره معتبر انتخاب کنید.",
                    confirmButtonText: "باشه",
                });
                return;
            }

            Swal.fire({
                title: "ارسال پیامک سفارشی",
                html: `
                    <p>تعداد کاربران انتخاب شده: ${total}</p>
                    <p>تعداد شماره موبایل معتبر برای ارسال: ${count}</p>
                    <textarea id="pwsms_message" class="swal2-textarea" placeholder="متن پیامک خود را وارد کنید..." style="width: 100%; margin: 0" rows="10" cols="20"></textarea>
                `,
                showCancelButton: true,
                confirmButtonText: "ارسال پیامک",
                cancelButtonText: "انصراف",
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const message = document.getElementById("pwsms_message").value.trim();
                    if (!message) {
                        Swal.showValidationMessage("لطفاً متن پیامک را وارد کنید.");
                        return false;
                    }

                    const data = {
                        mobile: mobiles,
                        message: message,
                    };

                    return fetch(pwsms_vendors.rest_url + "pwsms/sms/send", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-WP-Nonce": pwsms_vendors.nonce,
                        },
                        body: JSON.stringify(data),
                    })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error("وضعیت HTTP " + response.status);
                            }
                            return response.json();
                        })
                        .catch((error) => {
                            Swal.showValidationMessage("خطا در ارسال: " + error.message);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading(),
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const data = result.value;
                    if (data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "ارسال موفق",
                            text: `${data.message || "پیامک ارسال شد."} (${data.count} شماره)`,
                            confirmButtonText: "باشه",
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "خطا در ارسال",
                            text: data.error || "پیامک ارسال نشد.",
                            confirmButtonText: "باشه",
                        });
                    }
                }
            });
        }
    });
});
