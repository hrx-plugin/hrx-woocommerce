(function($) {

    $(document).ready(function() {
        $(document).on("change", ".hrx-method-terminal select", function() {
            hrxAjax.save_terminal(this.value, "add_terminal");
        });

        $(document).on("updated_checkout updated_shipping_method", function() {
            hrx_init_map();
        });

        hrx_init_map();
    });

})(jQuery);

function hrx_init_map() {
    var container_parcel_terminal = document.getElementById("hrx-method-terminal-map");
    if ( typeof(container_parcel_terminal) != "undefined" && container_parcel_terminal != null ) {
        if ( container_parcel_terminal.innerHTML === "" ) {
            hrxMap.init(container_parcel_terminal, hrx_terminal_terminals);
        } else {
            hrxMap.update_list();
        }
    }
}
