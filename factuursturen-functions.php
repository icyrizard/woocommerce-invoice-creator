<?php
    # factuursturen.nl api
    include_once("fsnl-api/fsnl_api.class.php");
    /* store credentials, only done after the first call*/
    $cred = null;

    /**
     * create the right url depending on the section of the api is needed. Note
     * only that a list of supported urls are available.
     * */
    function create_url($api_section, $API_URL, $API_VERSION){
        $url_list = array(
            "clients" => '/clients/',
            "products" => '/products/',
            "invoices" => '/invoices/'
        );

        return $API_URL . $API_VERSION . $url_list[$api_section];
    }

   /**
    * Get credentials from db. Settings can be changed in the admin page
    */
    function get_credentials(){
        global $wpdb, $cred;
        if($cred){
            return $cred;
        }
        $table = $wpdb->prefix . 'factuur_settings';

        $cred = $wpdb->get_row( $wpdb->prepare( "
            SELECT 	    api_url, api_key, api_version, username, textinvoice
            FROM 		{$wpdb->prefix}factuursturen_settings;
        "));

        return $cred;
    }

    /**
     * Initiated connection with factuursturen.nl, takes use of fsnl api
     * provided by factuursturen. Needs url, method(GET/POST), username,
     * password.
     */
    function set_connection($url, $method, $body=""){
        /*fail if not provided*/
        if (empty($url) || empty($method)){
            return new WP_Error("set_connection error", "No url or method given", [$url, $method]);
        }

        $cred = get_credentials();
        if (empty($cred->api_url) || empty($cred->api_version)){
            return new WP_Error("set_connection error", "No api_url or version given", [$cred->url, $cred->method]);
        }

        if (empty($cred->username) || empty($cred->api_key)){
            return new WP_Error("set_connection error", "No username or api_key given", [$cred->username, $cred->api_key]);
        }
        $url = create_url($url, $cred->api_url, $cred->api_version);

        /* initiate connection object */
        $request = new fsnl_api($url, $method);
        $request->setUsername($cred->username);
        $request->setPassword($cred->api_key);

        return $request;
    }
    /**
     * Get tax rate depending on tax class and other variables
     * Depending on the tax class, the rates differ so need to be
     * obtained via the database. Default tax is 21, provide an accurate
     * tax rate in the woocommerce -> settings -> Tax and select it when
     * creating a product.
     */
    function get_tax_rates($product_obj, $order){
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

    /* create request body for an invoice api call with data from the order
     * and clientid */
    function create_invoice_body($clientnr, $order){
        global $cred;
        $product_lines = array();
        $product_factory  = new WC_Product_Factory();
        foreach($order->get_items() as $p){
            $product_obj = $product_factory->get_product($p['product_id']);
            $p_line = array(
                "amount" => $p["qty"],
                "description" => $p["name"],
                "tax_rate" => get_tax_rates($product_obj, $order),
                "price" => $product_obj->get_price_excluding_tax(),
            );

            /* get fields, filter out vars with underscores and
             * total_sales */
            foreach(get_post_custom($p['product_id']) as $key => $value){
                if ($key[0] != "_" && $key != "total_sales"){
                    $p_line["description"] .= '\n' .  $key . ': ' . $value[0];
                }
            }

            array_push($product_lines, $p_line);
        }

        return array(
            'clientnr' => $clientnr,
            'action' => 'send',
            'reference' => array(
                'line1' => $cred->textinvoice,
            ),
            'lines' => $product_lines,
            'sendmethod' => 'email',
            'paiddate' => date('Y-m-d')
        );
    }

    function create_client_body($order){
        # countries mapping, map country codes to countries
        include_once("countries.php");

        return array(
            'contact' => $order->billing_email,
            'showcontact' => true,
            'company' => $order->billing_company,
            'address' => $order->billing_address_1,
            'zipcode' => $order->billing_postcode,
            'city' => $order->billing_city,
            'country' => $OFFICIAL_COUNTRIES[$order->billing_country],
            'phone' => $order->billing_phone,
            'mobile' => $order->billing_phone,
            'email' => $order->billing_email,
            'bankcode' => '',
            'taxnumber' => '',
            'tax_shifted' => false,
            'sendmethod' => 'email',
            'paymentmethod' => 'bank',
            'mailintro' => 'Geachte '. $order->billing_first_name . " " . $order->billing_last_name,
            'reference' => array(
                'line1' => 'Bedankt voor uw bestelling'
            ),
            'notes_on_invoice' => false,
        );
    }

    /**
     * validate if user is in the clients array, obtained by the clients/ api
     * call.
     * */
    function user_exists($clients, $order){
        if($clients){
            foreach(json_decode($clients, true) as $c){
                if($c["contact"] == $order->billing_email){
                    return $c['clientnr'];
                }
            }
        }
        return -1;
    }

    /* get clientnr, and create a new client if client doesnt exist. */
    function get_clientnr($order){
        /* get all clients */
        if(is_wp_error($request = set_connection("clients", "GET"))){
            return $request;
        }
        $request->execute();
        $result = $request->getResponseBody();

        if(($clientnr = user_exists($result, $order)) == -1){
            /* add new client */
            if(is_wp_error($request = set_connection("clients", "POST"))){
                return $request;
            }
            $request->buildPostBody(create_client_body($order));
            $request->execute();
            $result = $request->getResponseBody();
            $clientnr = $result;
        }

        return $clientnr;
    }

    /* send invoice to factuursturen.nl. */
    function send_invoice($order_id){
        if(!$order_id){
            return;
        }
        $order = new WC_Order($order_id);

        if(!($clientnr = get_clientnr($order))){
            return;
        }

        /* create request object */
        if(is_wp_error(($request = set_connection("invoices", "POST")))){
            error_log($request->get_error_message(), 0);
            return;
        }
        /* build invoice body */
        $request->buildPostBody(create_invoice_body($clientnr, $order));

        /* send it #TODO: test if this fails... */
        $request->execute();
        if ($request->getResponseBody()){
            error_log("Invoice is send", 0);
        }
    }

    add_action('woocommerce_order_status_completed', 'send_invoice');
?>

