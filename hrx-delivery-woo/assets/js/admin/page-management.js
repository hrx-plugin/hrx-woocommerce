(function($) {

    $(document).ready(function() {
        $(document).on('change', "#hrx-per_page", function() {
            window.location = hrxHelper.add_param_to_url('per_page', $(this).val(), ["per_page", "paged"]);
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
            var checked_rows = $("#" + this.dataset.table + " tr.data-row .column-cb input:checked");
            var selected_orders = [];

            for ( var i = 0; i < checked_rows.length; i++ ) {
                selected_orders.push(checked_rows[i].value);
            }

            hrxAjax.execute_table_mass_button(this, this.value, selected_orders);
        });
    });

})(jQuery);
