(function($) {

    window.hrxMap = {
        lib: null,
        init: function( container, terminals ) {
            this.lib = new HrxMapping();
            
            let method_key = container.dataset.method;
            let selected_field = document.getElementById("hrx-method-" + method_key + "-selected");

            this.lib.setImagesPath(hrxGlobalVars.img_url + "map/");
            this.lib.setTranslation(hrxGlobalVars.txt.mapModal);
            this.lib.dom.setContainerParent(container);
            
            this.lib.setParseLocationName(( location ) => {
                return location.name;
            });

            this.lib.setParseMapTooltip(( location, leafletCoords ) => {
                return location.address + ', ' + location.city;
            });

            this.lib.sub("tmjs-ready", function(data) {
                let selected_location = data.map.getLocationById(selected_field.value);
                if (typeof(selected_location) != 'undefined' && selected_location != null) {
                    hrxMap.lib.dom.setActiveTerminal(selected_location);
                    hrxMap.lib.publish('terminal-selected', selected_location);
                }
            });

            this.lib.sub("terminal-selected", function(data) {
                selected_field.value = data.id;
                hrxMap.lib.dom.setActiveTerminal(data.id);
                hrxMap.lib.publish("close-map-modal");
                console.log("HRX: Saving selected terminal...");
                hrxAjax.save_terminal(data.id, "add_terminal");
                console.log("HRX: Terminal changed to " + data.id);
            });

            this.lib.init({
                country_code: container.dataset.country,
                identifier: "hrx",
                isModal: true,
                modalParent: container,
                hideContainer: true,
                hideSelectBtn: true,
                cssThemeRule: "tmjs-default-theme",
                terminalList: terminals,
                customTileServerUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            });

            hrxMap.update_list();
        },

        update_list: function() {
            var selected_postcode = this.get_postcode();

            this.lib.dom.searchNearest(selected_postcode);
            this.lib.dom.UI.modal.querySelector('.tmjs-search-input').value = selected_postcode;
        },

        get_postcode: function() {
            var postcode = "";
            var ship_to_dif_checkbox = document.getElementById("ship-to-different-address-checkbox");
            
            if ( typeof(ship_to_dif_checkbox) != "undefined" && ship_to_dif_checkbox != null && ship_to_dif_checkbox.checked ) {
                postcode = document.getElementById("shipping_postcode").value;
            } else {
                postcode = document.getElementById("billing_postcode").value;
            }

            return postcode;
        }
    };

})(jQuery);
