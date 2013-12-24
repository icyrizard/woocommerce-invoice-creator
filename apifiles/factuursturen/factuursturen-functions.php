<?php
/**
 * Acumulus functions to send the invoice package to the Acumulus API.
 */
/* common functions needed to connect to woocommerce */
$PLUGIN_DIR = dirname(dirname(dirname(__FILE__)));
include_once($PLUGIN_DIR ."/invoice_creator-common.php");

/* fsnl api file, make sure you download these from:
 * http://www.factuursturen.nl/docs/fsnl-api.zip and extract it in
 * this directory(or change the include path if needed). */
include_once("fsnl-api/fsnl_api.class.php");

function elog($v){
    error_log(var_export($v, true), 0);
}
/* store credentials, only done after the first call*/
$cred = null;

/**
* create the right url depending on the section of the api is needed. Note * only that a list of supported urls are available.
* */
function fspl_create_url($api_section, $api_url, $api_version){
    $url_list = array(
        "clients" => '/clients/',
        "products" => '/products/',
        "invoices" => '/invoices/'
    );

    return $api_url . $api_version. $url_list[$api_section];
}


/**
* Initiated connection with factuursturen.nl, takes use of fsnl api
* provided by factuursturen. Needs url, method(GET/POST), username,
* password.
*/
function fspl_set_connection($url, $method, $body=""){
    /*fail if not provided*/
    if (empty($url) || empty($method)){
        $arr = array($url, $method);
        throw new Exception("set_connection error: no url or method given");
    }

    $cred = invc_get_credentials();
    if (empty($cred->api_url) || empty($cred->api_version)){
        $arr = array($cred->url, $cred->method);
        throw new Exception("set_connection error: no api_url or version given");
    }

    if (empty($cred->username) || empty($cred->api_key)){
        $arr = array($cred->username, $cred->api_key);
        throw new Exception("set_connection error: no username or api_key given");
    }
    $url = fspl_create_url($url, $cred->api_url, $cred->api_version);

    /* initiate connection object */
    $request = new fsnl_api($url, $method);
    $request->setUsername($cred->username);
    $request->setPassword($cred->api_key);

    return $request;
}

/* create request body for an invoice api call with data from the order
* and clientid */
function fspl_create_invoice_body($clientnr, $order){
    $product_lines = invc_generate_productlines($order);

    return array(
        'clientnr' => $clientnr,
        'action' => 'send',
        'reference' => array(),
        'lines' => $product_lines,
        'sendmethod' => 'email',
        'paiddate' => date('Y-m-d')
    );
}

function fspl_create_client_body($order){
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
        'mailintro' => 'Geachte '. invc_get_fullname($order),
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
function fspl_user_exists($clients, $order){
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
function fspl_get_clientnr($order){
    /* get all clients */
    $request = fspl_set_connection("clients", "GET");
    $request->execute();
    $result = $request->getResponseBody();

    /* add new client if non-existing */
    if(($clientnr = fspl_user_exists($result, $order)) == -1){
        $request = fspl_set_connection("clients", "POST");
        $request->buildPostBody(fspl_create_client_body($order));
        $request->execute();
        $result = $request->getResponseBody();
        $clientnr = $result;
    }

    return $clientnr;
}

/* send invoice to factuursturen.nl. */
function invoice_creator_send_invoice($order_id){
    if(!$order_id){
        return;
    }

    /* init woocommerce order */
    $order = new WC_Order($order_id);

    try {
        /* get client nmr */
        $clientnr = fspl_get_clientnr($order);
        /* create request object */
        $request = fspl_set_connection("invoices", "POST");
    } catch(Exception $e) {
        error_log('Caught Exception: ' . $e->getMessage(), 0);
        return;
    }

    /* build invoice body */
    $request->buildPostBody(fspl_create_invoice_body($clientnr, $order));

    /* send it #TODO: test if this fails... */
    $request->execute();
    if ($request->getResponseBody()){
        error_log("Invoice is sent", 0);
    }
}

?>
