<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

class ShippingMethodHelper
{
    public static function get_cart_amount()
    {
        global $woocommerce;
        
        return $woocommerce->cart->cart_contents_total + $woocommerce->cart->tax_total;
    }

    public static function get_cart_products_weight( $products )
    {
        $weight = 0;

        foreach ( $products as $item_id => $item_values ) {
            $product = $item_values['data'];
            if ( $product->get_weight() ) {
                $weight = $weight + $product->get_weight() * $item_values['quantity'];
            }
        }

        return wc_get_weight($weight, 'kg');
    }

    public static function get_price_by_weight( $prices_list, $current_weight )
    {
        $price = false;
        foreach ( $prices_list as $price_data ) {
            /* Skip if price not set */
            if ( ! isset($price_data['price']) ) {
                continue;
            }
            /* Skip if price empty */
            if ( empty($price_data['price']) && $price_data['price'] !== 0 && $price_data['price'] !== '0' ) {
                continue;
            }
            /* Skip if range ...-YY and weight > YY */
            if ( empty($price_data['w_from']) && ! empty($price_data['w_to']) && $current_weight > $price_data['w_to'] ) {
                continue;
            }
            /* Skip if range XX-... and weight < XX */
            if ( ! empty($price_data['w_from']) && empty($price_data['w_to']) && $current_weight < $price_data['w_from'] ) {
                continue;
            }
            /* Skip if range XX-YY and weight < XX or weight > YY */
            if ( ! empty($price_data['w_from']) && ! empty($price_data['w_to']) && ($current_weight > $price_data['w_to'] || $current_weight < $price_data['w_from']) ) {
                continue;
            }

            $price = (! empty($price_data['price'])) ? (float)$price_data['price'] : 0;
            break; // Only the value of the first valid range is returned
        }

        return $price;
    }
}
