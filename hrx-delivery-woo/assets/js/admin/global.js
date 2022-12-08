(function($) {

    window.hrxHelper = {
        toggle_show_by_cb: function( checkbox, elem_selector, hide_on_checked = false ) {
            var elem = $(elem_selector);

            if ( $(checkbox).is(":checked") ) {
                if ( hide_on_checked ) {
                    elem.hide();
                } else {
                    elem.show();
                }
            } else {
                if ( hide_on_checked ) {
                    elem.show();
                } else {
                    elem.hide();
                }
            }
        },

        toggle_class_by_cb: function( checkbox, elem_selector, class_name, add_on_checked = true ) {
            var elem = $(elem_selector);
            
            if ( $(checkbox).is(":checked") ) {
                if ( add_on_checked ) {
                    elem.addClass(class_name);
                } else {
                    elem.removeClass(class_name);
                }
            } else {
                if ( add_on_checked ) {
                    elem.removeClass(class_name);
                } else {
                    elem.addClass(class_name);
                }
            }
        },

        is_url: function( string ) {
            let url;
  
            try {
                url = new URL(string);
            } catch (_) {
                return false;  
            }

            return url.protocol === "http:" || url.protocol === "https:"
        }
    };

    window.hrxAjax = {
        execute_button_action: function( button, action, output_to = false ) {
            var button = $(button);
            button.addClass("hrx-loading").prop("disabled", true);

            setTimeout(function (){
                $.ajax({
                    type: "POST",
                    url: hrxGlobalVars.ajax_url,
                    dataType: "json",
                    async: false,
                    data: {
                        action: "hrx_" + action
                    },
                    success: function( response ) {
                        if ( output_to ) {
                            $(output_to).removeClass("value-empty");
                            $(output_to).removeClass("value-old");
                            
                            if ( response.status == "error" ) {
                                $(output_to).addClass("value-empty");
                            }
                            
                            $(output_to).html(response.msg);
                        }
                    },
                    error: function( xhr, status, error ) {
                        if ( output_to ) {
                            $(output_to).addClass("value-empty");
                            $(output_to).html(hrxGlobalVars.txt.request_error + " - " + error);
                        }
                    },
                    complete: function() {
                        button.removeClass("hrx-loading").prop("disabled", false);
                    }
                });
            }, 500);
        },

        execute_table_mass_button: function( button, mass_action, selected_orders, show_alert_on_error = true ) {
            var button = $(button);
            button.addClass("hrx-loading").prop("disabled", true);

            if ( ! selected_orders.length ) {
                console.log("Action error:", "No orders selected");
                if ( show_alert_on_error ) {
                    alert(hrxGlobalVars.txt.orders_not_selected);
                }
                button.removeClass("hrx-loading").prop("disabled", false);
                return;
            }

            $.ajax({
                type: "POST",
                url: hrxGlobalVars.ajax_url,
                dataType: "json",
                async: false,
                data: {
                    action: "hrx_table_mass_action",
                    mass_action: mass_action,
                    selected_orders: selected_orders,
                },
                success: function( response ) {
                    //console.log(response);
                    if ( show_alert_on_error && response.status == "error" ) {
                        if ( confirm(response.msg + "\n\n" + hrxGlobalVars.txt.alert_retry) ) {
                            hrxAjax.execute_table_mass_button(button, mass_action, selected_orders, show_alert_on_error);
                        }
                    }
                    if ( response.status == "OK" ) {
                        if ( hrxHelper.is_url(response.file) ) {
                            window.open(response.file, '_blank').focus();
                        } else if ( show_alert_on_error ) {
                            alert(hrxGlobalVars.txt.label_download_fail);
                        }
                    }
                },
                error: function( xhr, status, error ) {
                    console.log("Action error:", error);
                    if ( show_alert_on_error ) {
                        alert(hrxGlobalVars.txt.table_action_error + ":\n" + error);
                    }
                },
                complete: function() {
                    button.removeClass("hrx-loading").prop("disabled", false);
                }
            });
        },

        change_default_warehouse: function( warehouse_id, show_alert = true ) {
            $.ajax({
                type: "POST",
                url: hrxGlobalVars.ajax_url,
                dataType: "json",
                async: false,
                data: {
                    action: "hrx_change_default_warehouse",
                    warehouse: warehouse_id
                },
                success: function( response ) {
                    if ( show_alert ) {
                        alert(response);
                    }
                },
                error: function( xhr, status, error ) {
                    console.log("Warehouse change error:", error);
                    if ( show_alert ) {
                        alert(hrxGlobalVars.txt.warehouse_change_error);
                    }
                }
            });
        },

        create_hrx_order: function( button, hide_buttons = [], show_buttons = [], show_alert_on_error = true ) {
            var button = $(button);
            var order_id = button.closest(".data-row").attr("data-id");
            
            button.addClass("hrx-loading").prop("disabled", true);

            $.ajax({
                type: "POST",
                url: hrxGlobalVars.ajax_url,
                dataType: "json",
                async: false,
                data: {
                    action: "hrx_create_order",
                    order_id: order_id
                },
                success: function( response ) {
                    if ( show_alert_on_error && response.status == "error" ) {
                        if ( confirm(response.msg + "\n\n" + hrxGlobalVars.txt.alert_retry) ) {
                            hrxAjax.create_hrx_order(button, hide_buttons, show_buttons, show_alert_on_error);
                        } else {
                            location.reload();
                        }
                    }
                    if ( response.status == "OK" ) {
                        for ( var i = 0; i < hide_buttons.length; i++) {
                            button.closest(".data-row").find(hide_buttons[i]).hide();
                        }
                        for ( var i = 0; i < show_buttons.length; i++) {
                            button.closest(".data-row").find(show_buttons[i]).show();
                        }
                        if ( show_alert_on_error ) {
                            alert(response.msg);
                            location.reload();
                        }
                    }
                },
                error: function( xhr, status, error ) {
                    console.log("Action error:", error);
                    if ( show_alert_on_error ) {
                        if ( confirm(hrxGlobalVars.txt.table_action_error + "\n\n" + hrxGlobalVars.txt.alert_retry) ) {
                            hrxAjax.create_hrx_order(button, hide_buttons, show_buttons, show_alert_on_error);
                        }
                    }
                },
                complete: function() {
                    button.removeClass("hrx-loading").prop("disabled", false);
                }
            });
        },

        get_hrx_label: function( button, label_type, show_alert_on_error = true ) {
            var button = $(button);
            var order_id = button.closest(".data-row").attr("data-id");
            
            button.addClass("hrx-loading").prop("disabled", true);

            $.ajax({
                type: "POST",
                url: hrxGlobalVars.ajax_url,
                dataType: "json",
                async: false,
                data: {
                    action: "hrx_get_label",
                    order_id: order_id,
                    label_type: label_type,
                },
                success: function( response ) {
                    if ( show_alert_on_error && response.status == "error" ) {
                        if ( confirm(response.msg + "\n\n" + hrxGlobalVars.txt.alert_retry) ) {
                            hrxAjax.get_hrx_label(button, label_type, show_alert_on_error);
                        }
                    }
                    if ( response.status == "OK" ) {
                        console.log("Hrx label", response.label);
                        if ( hrxHelper.is_url(response.label) ) {
                            window.open(response.label, '_blank').focus();
                        } else if ( show_alert_on_error ) {
                            alert(hrxGlobalVars.txt.label_download_fail);
                        }
                    }
                },
                error: function( xhr, status, error ) {
                    console.log("Action error:", error);
                    if ( show_alert_on_error ) {
                        alert(hrxGlobalVars.txt.table_action_error + ":\n" + error);
                    }
                },
                complete: function() {
                    button.removeClass("hrx-loading").prop("disabled", false);
                }
            });
        },

        ready_hrx_order: function( button, mark_ready, hide_buttons = [], show_buttons = [], show_alert_on_error = true ) {
            var button = $(button);
            var order_id = button.closest(".data-row").attr("data-id");
            
            button.addClass("hrx-loading").prop("disabled", true);

            $.ajax({
                type: "POST",
                url: hrxGlobalVars.ajax_url,
                dataType: "json",
                async: false,
                data: {
                    action: "hrx_ready_order",
                    order_id: order_id,
                    ready: mark_ready,
                },
                success: function( response ) {
                    if ( show_alert_on_error && response.status == "error" ) {
                        if ( confirm(response.msg + "\n\n" + hrxGlobalVars.txt.alert_retry) ) {
                            hrxAjax.ready_hrx_order(button, mark_ready, hide_buttons, show_buttons, show_alert_on_error);
                        } else {
                            location.reload();
                        }
                    }
                    if ( response.status == "OK" ) {
                        for ( var i = 0; i < hide_buttons.length; i++) {
                            button.closest(".data-row").find(hide_buttons[i]).hide();
                        }
                        for ( var i = 0; i < show_buttons.length; i++) {
                            button.closest(".data-row").find(show_buttons[i]).show();
                        }
                        if ( show_alert_on_error ) {
                            if ( confirm(response.msg + "\n\n" + hrxGlobalVars.txt.reload_confirmation) ) {
                                location.reload();
                            }
                        }
                    }
                },
                error: function( xhr, status, error ) {
                    console.log("Action error:", error);
                    if ( show_alert_on_error ) {
                        alert(hrxGlobalVars.txt.table_action_error + ":\n" + error);
                    }
                },
                complete: function() {
                    button.removeClass("hrx-loading").prop("disabled", false);
                }
            });
        }
    };

})(jQuery);
