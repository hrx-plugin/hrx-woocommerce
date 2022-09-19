(function($) {

    var hrxSwitcher = {
        labels_init: function() {
            $(".hrx-switcher").each(function( index ) {
                hrxSwitcher.label_toggle(this);
                hrxSwitcher.label_add_event(this);
            });
        },

        label_toggle: function( switcher ) {
            var switcher = $(switcher);
            var label = switcher.find(".label");
            
            if ( label.length == 0 ) {
                return;
            }
            
            if ( switcher.find("input[type='checkbox']").is(':checked') ) {
                label.text(label.data("on"));
                switcher.closest(".country_box").find(".box-content").removeClass("off");
            } else {
                label.text(label.data("off"));
                switcher.closest(".country_box").find(".box-content").addClass("off");
            }
        },

        label_add_event: function( switcher ) {
            $(switcher).find("input[type='checkbox']").on("change", {switcher: switcher}, function(e) {
                hrxSwitcher.label_toggle(e.data.switcher);
            });
        }
    };

    var hrxMethods = {
        init: function() {
            hrxMethods.add_cb_events();

            $(".hrx-method").trigger("change");
        },

        toggle_method: function( checkbox ) {
            var method_key = $(checkbox).attr("data-key");

            hrxHelper.toggle_show_by_cb(checkbox, ".hrx-method-" + method_key);
        },

        add_cb_events: function() {
            $(document).on("change", ".hrx-method", function() {
                hrxMethods.toggle_method(this);
            });
        }
    };

    var hrxButton = {
        prices_init: function() {
            $(document).on("click", ".prices_range .hrx-btn-add", function() {
                hrxButton.prices_add_new_row(this);
            });
            $(document).on("click", ".prices_range .hrx-btn-remove", function() {
                hrxButton.prices_remove_row(this);
            });
        },

        prices_add_new_row: function( button ) {
            var container = $(button).closest(".box-content");
            var first_elem = $(container).find(".prices_range.first");
            var new_elem = $(first_elem).clone();
            var key = $(new_elem).data("key");
            var name = $(new_elem).data("name");

            hrxButton.prices_prepare_new_block(new_elem, container, key, name);

            $(container).append(new_elem);
            $(button).hide();
        },

        prices_clear_input: function( parent, selector, key, name, subkey, value = "" ) {
            $(parent).find(selector).prop("id", key + "_" + subkey);
            $(parent).find(selector).prop("name", name + "[" + subkey + "]");
            $(parent).find(selector).val(value);
            $(parent).find(selector).prop("min", 0);
            $(parent).find(selector).prop("max", "");
        },

        prices_prepare_new_block: function( element, container, fields_key, fields_name ) {
            var element = $(element);
            var container = $(container);
            var row_id = 0;
            var total_rows = container.parent().find(".prices_range").length;
            for ( var i = 0; i <= total_rows; i++ ) {
                if ( ! $("#" + fields_key + "_" + i + "_price").length ) {
                    row_id = i;
                    break;
                }
            }

            var new_key = fields_key + "_" + row_id;
            var new_name = fields_name + "[" + row_id + "]";

            element.removeClass("first");
            element.find(".hrx-btn-add").show();
            hrxButton.prices_clear_input(element, ".range-row-price input[type='number']", new_key, new_name, "price");
            var prev_value = container.find(".prices_range:last").find(".range-row-weight_range .field-w_to").val();
            var next_value = 0;
            if ( prev_value >= 0 ) {
                next_value = parseFloat(prev_value.replace(",", ".")) + 0.001;
            }
            hrxButton.prices_clear_input(element, ".range-row-weight_range .field-w_from", new_key, new_name, "w_from", next_value);
            hrxButton.prices_clear_input(element, ".range-row-weight_range .field-w_to", new_key, new_name, "w_to");
            element.find(".range-row-actions .hrx-btn-remove").attr("data-id", row_id);
        },

        prices_remove_row: function( button ) {
            var button = $(button);
            var container = button.closest(".box-content");
            var this_elem = button.closest(".prices_range");
            var other_rows = $(this_elem).siblings(".prices_range");

            if ( this_elem.hasClass("first") ) {
                if ( other_rows.length ) {
                    $(other_rows[0]).addClass("first");
                }
            }
            other_rows.find(".hrx-btn-add").hide();
            $(other_rows[other_rows.length - 1]).find(".hrx-btn-add").show();

            if ( other_rows.length ) {
                this_elem.addClass("removing");
                this_elem.slideUp("slow", function(){ this_elem.remove(); });
            } else {
                this_elem.find(".range-row-price input[type='number']").val("");
                this_elem.find(".range-row-weight_range .field-w_from").val("");
                this_elem.find(".range-row-weight_range .field-w_to").val("");
            }
        }
    };

    var hrxValues = {
        validate_weight: function( this_field ) {
            var w_from = $(this_field).parent().find(".field-w_from");
            var w_to = $(this_field).parent().find(".field-w_to");

            if ( $(this_field).hasClass("field-w_from") ) {
                if ( $(w_to).val().length !== 0 && parseInt($(this_field).val()) > parseInt($(w_to).val()) ) {
                    $(this_field).val($(w_to).val());
                }

                if ( $(this_field).val().length !== 0 ) {
                    $(w_to).attr("min", $(this_field).val());
                } else {
                    $(w_to).attr("min", 0);
                }
            }

            if ( $(this_field).hasClass("field-w_to") ) {
                if ( $(w_from).val().length !== 0 && $(this_field).val().length !== 0 && parseInt($(this_field).val()) < parseInt($(w_from).val()) ) {
                    $(this_field).val($(w_from).val());
                }

                $(w_from).attr("max", $(this_field).val());
            }
        }
    };

    $(document).ready(function() {
        /* Load functions */
        hrxSwitcher.labels_init();
        hrxButton.prices_init();
        hrxMethods.init();

        $(document).on("change", ".apimode-toggle-cb", function() {
            hrxHelper.toggle_show_by_cb(this, ".apimode-toggle-test");
            hrxHelper.toggle_show_by_cb(this, ".apimode-toggle-live", true);
        });
        hrxHelper.toggle_show_by_cb(".apimode-toggle-cb", ".apimode-toggle-test");
        hrxHelper.toggle_show_by_cb(".apimode-toggle-cb", ".apimode-toggle-live", true);

        $(document).on("change", ".debug-toggle-cb", function() {
            hrxHelper.toggle_show_by_cb(this, ".debug-toggle");
        });
        hrxHelper.toggle_show_by_cb(".debug-toggle-cb",".debug-toggle");

        $(document).on("click", "#check_token_button", function() {
            hrxAjax.execute_button_action(this, this.value, "#check_token_span");
        });

        $(document).on("click", "#upd_pickup_loc_button", function() {
            hrxAjax.execute_button_action(this, this.value, "#upd_pickup_loc_span");
        });

        $(document).on("click", "#upd_delivery_loc_button", function() {
            hrxAjax.execute_button_action(this, this.value, "#upd_delivery_loc_span");
        });

        $(document).on("change", ".global-enable", function() {
            hrxHelper.toggle_class_by_cb(this, $(this).closest("tr").siblings("tr"), "row-disabled", false);
        });

        /* Last load functions */
        hrxHelper.toggle_class_by_cb(".global-enable", $(".global-enable").closest("tr").siblings("tr"), "row-disabled", false);

        /* Other functions */
        $(document).on("change", ".field-w_from, .field-w_to", function() {
            hrxValues.validate_weight(this);
        });
    });

})(jQuery);
