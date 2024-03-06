= Filters =
add_filter('hrx_delivery_checkout_method_title', function($title, $method, $country){return $title}, 10, 3) - Allows to change the title of the shipping method displayed on the Checkout page.
add_filter('hrx_delivery_checkout_method_price', function($price, $method, $country){return $price}, 10, 3) - Allows to change the price of the shipping method on the Checkout page.
