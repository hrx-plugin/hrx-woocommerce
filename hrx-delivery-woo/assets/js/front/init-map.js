(function($) {

    window.hrxMap = {
        init: function( container, terminals ) {
            const tmjs = new TerminalMapping();
            
            let method_key = container.dataset.method;
            let selected_field = document.getElementById("hrx-method-" + method_key + "-selected");

            tmjs.setImagesPath(hrxGlobalVars.img_url + "map/");
            tmjs.setTranslation(hrxGlobalVars.txt.mapModal);
            tmjs.dom.setContainerParent(container);
            
            tmjs.setParseLocationName(( location ) => {
                return location.name;
            });

            tmjs.setParseMapTooltip(( location, leafletCoords ) => {
                return location.address + ', ' + location.city;
            });

            tmjs.sub("tmjs-ready", function(data) {
                let selected_location = data.map.getLocationById(selected_field.value);
                if (typeof(selected_location) != 'undefined' && selected_location != null) {
                    tmjs.dom.setActiveTerminal(selected_location);
                    tmjs.publish('terminal-selected', selected_location);
                }
            });

            tmjs.sub("terminal-selected", function(data) {
                selected_field.value = data.id;
                tmjs.dom.setActiveTerminal(data.id);
                tmjs.publish("close-map-modal");
                console.log("HRX: Saving selected terminal...");
                hrxAjax.save_terminal(data.id, "add_terminal");
                console.log("HRX: Terminal changed to " + data.id);
            });

            tmjs.init({
                country_code: container.dataset.country,
                identifier: "hrx",
                isModal: true,
                modalParent: container,
                hideContainer: true,
                hideSelectBtn: true,
                cssThemeRule: "tmjs-default-theme",
                terminalList: terminals
            });
        }
    };

})(jQuery);
