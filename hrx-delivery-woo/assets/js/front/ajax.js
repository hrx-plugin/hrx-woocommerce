(function($) {

    window.hrxAjax = {
        save_terminal: function( value, action ) {
            $.ajax({
                type: "POST",
                url: hrxGlobalVars.ajax_url,
                dataType: "json",
                async: false,
                data: {
                    action: "hrx_" + action,
                    terminal_id: value
                },
                success: function( response ) {
                    console.log(response);
                },
                error: function( xhr, status, error ) {

                }
            });
        }
    };

})(jQuery);
