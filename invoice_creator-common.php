<?php
/**
* Invoice-creator-common provides functionalities that can be used
* to get information from the database(e.g, credentials), or easier to
* use functions that get information from woocommerce.
*
*/
include_once($SETTINGS_FILE);

/* store credentials, only done after the first call*/
$cred = null;

/* concatenate first and lastname */
function invc_get_fullname($order){
    return $order->billing_first_name . " " . $order->billing_last_name;
}

/**
* Get credentials from db. Settings can be changed in the admin page
*/
function invc_get_credentials(){
    global $wpdb, $cred, $TABLE_FIELDS, $API_NAME;
    if($cred){
        return $cred;
    }

    $table = $wpdb->prefix . "invoice_creator";
    /* default fields */
    $fields = array('textinvoice', 'exclude_custom_fields', 'api_name');

    /* get fields */
    foreach($TABLE_FIELDS as $name => $additional){
        array_push($fields, $name);
    }

    /* comma seperated string as prep for select query */
    $fields = implode(',', $fields);

    /* Select fields as defined in settings.php */
    $options = $wpdb->get_row( $wpdb->prepare("
        SELECT {$fields}
        FROM {$wpdb->prefix}invoice_creator",0)
    );

    return $options;
}

/**
* Get tax rate depending on tax class and other variables
* Depending on the tax class, the rates differ so need to be
* obtained via the database. Default tax is 21, provide an accurate
* tax rate in the woocommerce -> settings -> Tax and select it when
* creating a product.
*
* This function need to be fully defined at a later point. There a some
* issues in woocommerce that do not catch all cases. So for simplicity
* this function is just set to default BTW in the netherlands.
*/
function invc_get_tax_rates($product_obj, $order){
    /* WC_Tax object */
    $taxes = new WC_Tax();
    $tax_rates = $taxes->find_rates(
        array(
            "tax_class" => $product_obj->get_tax_class(),
            "country" => $order->billing_country,
            "postcode" => $order->billing_postcode
        ));

    /* return the first one, should be the only one that matches best */
    $tax_percentage = 21.00;
    if (!empty($tax_rates)){
        $first_index = reset($tax_rates);
        $tax_percentage = $first_index['rate'];
    }

    return $tax_percentage;
}

/**
* Get tax rate depending on tax class and other variables
* Depending on the tax class, the rates differ so need to be
* obtained via the database. Default tax is 21, provide an accurate
* tax rate in the woocommerce -> settings -> Tax and select it when
* creating a product.
*
* @return - percentage (float)
*/
function invc_get_shiptax_rates(){
    $tax_percentage = 21.00;
    return $tax_percentage;
}

/* Get important fields from all products inside the order.
 *
 * @param $order - WC_Order ( created by,  new WC_Order($order_id))
 * @return array dict -
 *            "product_id" - product_id,
 *            "amount" - quanity of this product inside the order,
 *            "description" - description of the order, description
 *                      is further extended by the custom fields, given
 *                      to the product. See settings page in admin
 *                      section to exclude certain fields.
 *            "tax_rate" - tax_rate, given by setting the tax rate in
 *                            woocommerce and adding it to the product.
 *            "price" -  price of product,
 *
 *       Note that a product of send_rate is slapped on as an extra
 *       product to the order.
 * */
function invc_generate_productlines($order){
    $cred = invc_get_credentials();
    $product_lines = array();
    $product_factory = new WC_Product_Factory();

    $exclude_fields = explode(';', $cred->exclude_custom_fields);

    foreach($order->get_items() as $p){
        $product_obj = $product_factory->get_product($p['product_id']);

        $p_line = array(
            "product_id" => $p['product_id'],
            "amount" => $p["qty"],
            "description" => $p["name"],
            "tax_rate" => number_format((float)invc_get_tax_rates($product_obj, $order), 2, '.', ''),
            "price" => $product_obj->get_price_excluding_tax(),
        );

        /**
         * Get fields, filter out vars with underscores and
         * total_sales */
        foreach(get_post_custom($p['product_id']) as $key => $value){
            /* ignore if exclude fields */
            if (in_array($key, $exclude_fields)){
                continue;
            }

            /**
             * Do not include custom fields starting with _ or
             * total sales, they are used in woocommerce
             */
            if ($key[0] != "_" && $key != "total_sales"){
                $p_line["description"] .= '\n' .  $key . ': ' . $value[0];
            }
        }
        array_push($product_lines, $p_line);
    }

    $s_line = array(
        "amount" => "1",
        "description" => "Shipping costs",
        "tax_rate" => number_format((float)invc_get_shiptax_rates(),2,'.',''),
        "price" => $order->get_shipping(),
    );

    /* combine send costs and products */
    array_push($product_lines, $s_line);
    return $product_lines;
}

/* obtained from http://stackoverflow.com/questions/3616540/format-xml-string*/
// debug function of xml body
function formatXmlString($xml) {
  // add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
  $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);

  // now indent the tags
  $token      = strtok($xml, "\n");
  $result     = ''; // holds formatted version as it is built
  $pad        = 0; // initial indent
  $matches    = array(); // returns from preg_matches()

  // scan each line and adjust indent based on opening/closing tags
  while ($token !== false) :

    // test for the various tag states

    // 1. open and closing tags on same line - no change
    if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) :
      $indent=0;
    // 2. closing tag - outdent now
    elseif (preg_match('/^<\/\w/', $token, $matches)) :
      $pad--;
    // 3. opening tag - don't pad this one, only subsequent tags
    elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
      $indent=1;
    // 4. no indentation needed
    else :
      $indent = 0;
    endif;

    // pad the line with the required number of leading spaces
    $line    = str_pad($token, strlen($token)+$pad, ' ', STR_PAD_LEFT);
    $result .= $line . "\n"; // add to the cumulative result, with linefeed
    $token   = strtok("\n"); // get the next token
    $pad    += $indent; // update the pad size for subsequent lines
  endwhile;

  return $result;
}
?>
