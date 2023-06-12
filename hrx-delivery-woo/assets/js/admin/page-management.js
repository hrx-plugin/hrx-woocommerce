(function($) {

    $(document).ready(function() {
        $(document).on('change', "#hrx-per_page", function() {
            window.location = hrxHelper.add_param_to_url('per_page', $(this).val(), ["per_page", "paged"]);
        });

        $(document).on("click", ".hrx-open-modal", function() {
            if ( typeof $(this).closest(".data-row").data("id") === "undefined" ) {
                return false;
            }

            var modal_open_btn = this;
            var modal_id = this.dataset.modal;
            var message_modal_id = "message";
            var order_id = $(this).closest(".data-row").data("id");

            modal_open_btn.classList.add("hrx-loading");

            hrxAjax.get_wc_order_data(order_id, function(data) {
                if ( typeof data !== "object" || ! data.hasOwnProperty("wc_order") ) {
                    modal_open_btn.classList.remove("hrx-loading");
                    hrxModal.init(message_modal_id);
                    hrxModal.change_data_html(message_modal_id, "title", "Error");
                    hrxModal.change_data_html(message_modal_id, "message", "Failed to get Order data");
                    hrxModal.open(message_modal_id);
                    return;
                }
                hrxModal.init(modal_id);
                hrxModal.open(modal_id);
                hrxModal.change_data_html(modal_id, "title", data.wc_order.number);
                hrxModal.change_data_html(modal_id, "status", "<span>" + data.wc_order.status_text + "</span>");
                hrxModal.add_data_class(modal_id, "status", "status-" + data.wc_order.status);
                hrxModal.change_data_html(modal_id, "billing-name", data.wc_order.billing.fullname);
                hrxModal.change_data_html(modal_id, "billing-address", data.wc_order.billing.address);
                hrxModal.change_data_html(modal_id, "billing-city", data.wc_order.billing.city);
                hrxModal.change_data_html(modal_id, "billing-postcode", data.wc_order.billing.postcode);
                hrxModal.change_data_html(modal_id, "billing-country", data.wc_order.billing.country_name);
                hrxModal.change_data_html(modal_id, "billing-email", data.wc_order.billing.email);
                hrxModal.change_data_html(modal_id, "billing-phone", data.wc_order.billing.phone);
                hrxModal.change_data_html(modal_id, "billing-payment", data.wc_order.payment.title);
                hrxModal.change_data_html(modal_id, "billing-total-products", data.wc_order.payment.products);
                hrxModal.change_data_html(modal_id, "billing-total-shipping", data.wc_order.payment.shipping);
                hrxModal.change_data_html(modal_id, "billing-total-tax", data.wc_order.payment.tax);
                hrxModal.change_data_html(modal_id, "billing-total", data.wc_order.payment.total);
                hrxModal.change_data_html(modal_id, "shipping-name", data.wc_order.shipping.fullname);
                hrxModal.change_data_html(modal_id, "shipping-address", data.wc_order.shipping.address);
                hrxModal.change_data_html(modal_id, "shipping-city", data.wc_order.shipping.city);
                hrxModal.change_data_html(modal_id, "shipping-postcode", data.wc_order.shipping.postcode);
                hrxModal.change_data_html(modal_id, "shipping-country", data.wc_order.shipping.country_name);
                hrxModal.change_data_html(modal_id, "shipping-method", data.wc_order.shipment.method_title);
                hrxModal.change_data_html(modal_id, "shipping-terminal", data.wc_order.shipment.terminal_title);
                hrxModal.change_data_html(modal_id, "shipping-tracking", data.wc_order.shipment.tracking_number);
                hrxModal.change_data_html(modal_id, "shipping-warehouse", data.wc_order.shipment.warehouse_title);
                hrxModal.change_data_html(modal_id, "shipping-size", data.wc_order.shipment.weight + " " + data.wc_order.shipment.dimensions);
                hrxModal.remove_table_rows(modal_id, "products");
                var products = [];
                for ( var i = 0; i < data.wc_order.products.length; i++ ) {
                    products.push([
                        data.wc_order.products[i].name,
                        data.wc_order.products[i].sku,
                        data.wc_order.products[i].price,
                        data.wc_order.products[i].qty,
                        data.wc_order.products[i].total
                    ]);
                }
                hrxModal.add_table_rows(modal_id, "products", products);

                modal_open_btn.classList.remove("hrx-loading");
            });

            return false;
        });

        $(document).on("change", ".hrx-table-warehouses .column-selected input", function() {
            hrxAjax.change_default_warehouse(this.value);
        });

        $(document).on("click", ".btn-create_order", function() {
            hrxAjax.create_hrx_order(this, [".btn-unready_order"], [".btn-ready_order", ".btn-shipment_label", ".btn-return_label"]);
        });

        $(document).on("click", ".btn-shipment_label", function() {
            hrxAjax.get_hrx_label(this, "shipping");
        });

        $(document).on("click", ".btn-return_label", function() {
            hrxAjax.get_hrx_label(this, "return");
        });

        $(document).on("click", ".btn-ready_order", function() {
            hrxAjax.ready_hrx_order(this, true, ["#" + this.id], [".btn-unready_order"]);
        });

        $(document).on("click", ".btn-unready_order", function() {
            hrxAjax.ready_hrx_order(this, false, ["#" + this.id], [".btn-ready_order"]);
        });

        $(document).on("click", ".mass-buttons button", function() {
            let checked_rows = $("#" + this.dataset.table + " tr.data-row .column-cb input:checked");
            let selected_orders = [];

            for ( let i = 0; i < checked_rows.length; i++ ) {
                let hrx_status = checked_rows[i].dataset.hrxstatus;
                let wc_status = checked_rows[i].dataset.wcstatus;

                if ( hrxHelper.check_allowed_order_action(this.value, hrx_status, wc_status) ) {
                    selected_orders.push(checked_rows[i].value);
                }
            }

            hrxAjax.execute_table_mass_button(this, this.value, selected_orders);
        });
    });
    
})(jQuery);
