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
        },

        add_param_to_url: function( param_name, param_value, remove_old_params = [] ) {
            var url = window.location.href;
            
            if ( remove_old_params.length > 0 ) {
                for ( var i = 0; i < remove_old_params.length; ++i ) {
                    var pattern = new RegExp("[?&]" + remove_old_params[i] + "=([^&]+)");
                    url = url.replace(pattern, "");
                }
            }

            if ( url.indexOf("?") < 0 ) {
                url += "?" + param_name + "=" + param_value;
            } else {
                url += "&" + param_name + "=" + param_value;
            }
            
            return url;
        },

        check_allowed_order_action: function( action, hrx_status, wc_status ) {
            if ( ! (action in hrxGlobalVars.allowed_order_actions) ) {
                return true;
            }

            let allowed_actions = hrxGlobalVars.allowed_order_actions[action];

            if ( allowed_actions.hrx_not.length && allowed_actions.hrx_not.includes(hrx_status) ) {
                return false;
            }
            if ( allowed_actions.wc_not.length && allowed_actions.wc_not.includes(wc_status) ) {
                return false;
            }
            if ( allowed_actions.hrx.length && ! allowed_actions.hrx.includes(hrx_status) ) {
                return false;
            }
            if ( allowed_actions.wc.length && ! allowed_actions.wc.includes(wc_status) ) {
                return false;
            }

            return true;
        }
    };

    window.hrxModal = {
        modals: {},
        check_element: function( modal ) {
            return modal.length;
        },
        init: function( modal_id ) {
            const modal = $("#hrx-modal-" + modal_id);
            if ( ! this.check_element(modal) ) {
                return;
            }

            this.modals[modal_id] = modal;

            return hrxModal;
        },
        close: function( close_button ) {
            var modal = $(close_button).closest(".hrx-modal");
            if ( ! this.check_element(modal) ) {
                return;
            }
            modal.hide();
        },
        check: function( modal_id ) {
            return modal_id in this.modals;
        },
        open: function( modal_id ) {
            if ( this.check(modal_id) ) {
                this.modals[modal_id].show();
            }
        },
        change_data_html: function( modal_id, data_key, data_value ) {
            if ( ! this.check(modal_id) ) {
                return;
            }

            this.modals[modal_id].find(".modal-data-" + data_key).html(data_value);
        },
        add_data_class: function( modal_id, data_key, class_name ) {
            if ( ! this.check(modal_id) ) {
                return;
            }

            this.modals[modal_id].find(".modal-data-" + data_key).addClass(class_name);
        },
        remove_data_class: function( modal_id, data_key, class_name ) {
            if ( ! this.check(modal_id) ) {
                return;
            }

            this.modals[modal_id].find(".modal-data-" + data_key).removeClass(class_name);
        },
        add_table_rows: function( modal_id, data_key, rows_values = [] ) {
            if ( ! this.check(modal_id) || ! rows_values.length ) {
                return;
            }

            const table = this.modals[modal_id].find(".modal-data-" + data_key);
            if ( ! table.length ) {
                return;
            }

            for ( let i = 0; i < rows_values.length; i++ ) {
                let new_row = table[0].insertRow();
                for ( let j = 0; j < rows_values[i].length; j++ ) {
                    let new_cell = new_row.insertCell();
                    let text = rows_values[i][j];
                    if ( text == "" ) {
                        text = "—";
                    }
                    new_cell.innerHTML = text;
                }
            }
        },
        remove_table_rows: function( modal_id, data_key ) {
            if ( ! this.check(modal_id) ) {
                return;
            }

            this.modals[modal_id].find(".modal-data-" + data_key).find("tr:not(:first)").remove();
        }
    };

    window.hrxAjax = {
        execute_button_action: function( button, action, output_to = false ) {
            var button = $(button);
            button.addClass("hrx-loading").prop("disabled", true);

            if ( output_to ) {
                $(output_to).addClass("value-progress");
                $(output_to).html(hrxGlobalVars.txt.locations_progress + "... " + 0);
            }

            setTimeout(function (){
                hrxAjax.send_button_action(action, 1, button, output_to);
            }, 100);
        },

        send_button_action: function(action, page, button, output_to = false) {
            $.ajax({
                type: "POST",
                url: hrxGlobalVars.ajax_url,
                dataType: "json",
                async: false,
                data: {
                    action: "hrx_" + action,
                    page: page
                },
                success: function( response ) {
                    //console.log(response);
                    if ( response.repeat ) {
                        setTimeout(function (){
                            hrxAjax.button_action_ajax(action, page + 1, button, output_to);
                        }, 100);
                        if ( output_to ) {
                            $(output_to).html(hrxGlobalVars.txt.locations_progress + "... " + response.total);
                        }
                    } else {
                        if ( output_to ) {
                            $(output_to).removeClass("value-empty");
                            $(output_to).removeClass("value-old");
                            $(output_to).removeClass("value-progress");
                            
                            if ( response.status == "error" ) {
                                $(output_to).addClass("value-empty");
                            }
                            
                            $(output_to).html(response.msg);
                        }
                        button.removeClass("hrx-loading").prop("disabled", false);
                    }
                },
                error: function( xhr, status, error ) {
                    if ( output_to ) {
                        $(output_to).addClass("value-empty");
                        $(output_to).html(hrxGlobalVars.txt.request_error + " - " + error);
                    }
                    button.removeClass("hrx-loading").prop("disabled", false);
                },
                complete: function() {
                }
            });
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
                    if ( show_alert_on_error && response.status == "error" && response.msg !== "" ) {
                        if ( confirm(response.msg + "\n\n" + hrxGlobalVars.txt.alert_retry) ) {
                            hrxAjax.execute_table_mass_button(button, mass_action, selected_orders, show_alert_on_error);
                        } else {
                            location.reload();
                        }
                    }
                    if ( response.status == "OK" ) {
                        if ( "file" in response && response.file !== "" ) {
                            if ( hrxHelper.is_url(response.file) ) {
                                window.open(response.file, '_blank').focus();
                            } else if ( show_alert_on_error ) {
                                alert(hrxGlobalVars.txt.label_download_fail);
                            }
                        }
                        if ( "multi_msg" in response ) {
                            let alert_message = "";
                            if ( "successes" in response.multi_msg && response.multi_msg.successes.length ) {
                                for ( let i = 0; i < response.multi_msg.successes.length; i++ ) {
                                    alert_message += response.multi_msg.successes[i] + "\n";
                                }
                                alert_message += "\n";
                            }
                            if ( "errors" in response.multi_msg && response.multi_msg.errors.length ) {
                                for ( let i = 0; i < response.multi_msg.errors.length; i++ ) {
                                    alert_message += response.multi_msg.errors[i] + "\n";
                                }
                            }
                            if ( alert_message != "" ) {
                                if ( confirm(alert_message + "\n\n" + hrxGlobalVars.txt.alert_reload) ) {
                                    location.reload();
                                }
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
        },

        get_wc_order_data: function( order_id, callback ) {
            $.ajax({
                type: "POST",
                url: hrxGlobalVars.ajax_url,
                dataType: "json",
                async: false,
                data: {
                    action: "hrx_get_wc_order_data",
                    wc_order_id: order_id,
                },
                success: callback,
                error: callback
            });
        }
    };

})(jQuery);
