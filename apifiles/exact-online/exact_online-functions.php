<?php
/**
 * Exact Online functions to send the invoice package to the Exact Online API.
 */
/* common functions needed to connect to woocommerce */
$PLUGIN_DIR = dirname(dirname(dirname(__FILE__)));
include_once($PLUGIN_DIR ."/invoice_creator-common.php");
include_once("xml_formats.php");

function elog($v){
    error_log(var_export($v, true), 0);
}

/* api url based on the a key word */
function eo_build_url($api_section){
    $cred = invc_get_credentials();
    $cred->division = "545462";

    $url_list = array(
        "clear" => "/docs/ClearSession.aspx?Division=".
                    $cred->division."&Remember=3",
        'upload_items' => "/docs/XMLUpload.aspx?Topic=Items&output=1&ApplicationKey=". $cred->applicationkey,
        'divisions' => "/docs/XMLDivisions.aspx"
    );

    return $cred->api_url . $url_list[$api_section];
}


/* add header with credentials, used for authentication. */
function eo_add_header(){
    return '<?xml version="1.0" encoding="UTF-8"?>
<eExact xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="eExact-XML.xsd">';
}

/* generate product lines xml list from array */
function eo_product_lines_xml($product_lines){
    $p_xml = "";
    foreach($product_lines as $p){
        $p_xml .= "<line>";
        $p_xml .= "<itemnumber><![CDATA[". $p['product_id'] ."]]></itemnumber>";
        $p_xml .= "<product><![CDATA[". $p['description']. "]]></product>";
        $p_xml .= "<unitprice><![CDATA[". $p['price'] . "]]></unitprice>";
        $p_xml .= "<quantity><![CDATA[". $p["amount"] ."]]></quantity>";
        $p_xml .= "<costprice></costprice>"; # not used, see API doc.
        $p_xml .= "</line>";
    }

    return $p_xml;
}
/**
 * Create invoice body for exact-online, see exact-online api for fully
 * specification of this xml body.
 */
function eo_create_invoice_body($order){
    $cred = invc_get_credentials();

    /* see api documentation */
    $product_lines = invc_generate_productlines($order);

    $product_list_xml = eo_product_lines_xml($product_lines);

    $xml_body = "<customer>
        <type>1</type>
        <fullname><![CDATA[".invc_get_fullname($order)."]]></fullname>
        <companyname1><![CDATA[$order->billing_company]]></companyname1>
        <address1><![CDATA[$order->billing_address_1]]></address1>
        <address2><![CDATA[$order->billing_address_2]]></address2>
        <postalcode><![CDATA[$order->billing_postcode]]></postalcode>
        <city><![CDATA[$order->billing_city]]></city>
        <countrycode><![CDATA[". $order->billing_country ."]]></countrycode>
        <telephone><![CDATA[$order->billing_phone]]></telephone>
        <email><![CDATA[$order->billing_email]]></email>
        <invoice>
            <concept>0</concept>
            <issuedate><![CDATA[".date('Y-m-d') ."]]></issuedate>
            <paymentstatus>2</paymentstatus>
            <paymentdate><![CDATA[" .date('Y-m-d') ."]]></paymentdate>
            <description>$<![CDATA[$cred->textinvoice]]></description>
            $product_list_xml
            <emailaspdf>
                <emailto><![CDATA[$order->billing_email]]></emailto>
                <emailfrom><![CDATA[" . get_bloginfo('admin_email') ."]]></emailfrom>
                <subject><![CDATA[Bevestiging aankoop ". $order->get_order_number() ."]]></subject>
                <message><![CDATA[Bevestiging aankoop ". $order->get_order_number() ."]]></message>
            </emailaspdf>
        </invoice>
    </customer>";

    return $xml_body;
}

/**
 * Clear session. Request to exact online with
 * Username and password from credentials. Set
 * connecftion to Keep-Alive. Then, use the connection
 * to perform a xml upload.
 */
function eo_clear_session($cookie){
    $cred = invc_get_credentials();

    /* Logging in */
    $header[1] = "Cache-Control: private";
    $header[2] = "Connection: Keep-Alive";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
                array("_UserName_"=>$cred->username,
                "_Password_"=>$cred->password));
    curl_setopt($ch, CURLOPT_URL, eo_build_url("divisions"));
    curl_exec($ch);
    return $ch;
}

/* send function via curl
 * @param xml_string - xml body,
 *               url - url of exact-online.
 * */
function eo_send_msg($xml_string, $url, $loc){
    $cred = invc_get_credentials();
    $xml_string = utf8_encode($xml_string);
    $cookie_loc = "$loc/cookie.txt";

    /* clear session and start session with username password*/
    $ch = eo_clear_session($cookie_loc);

    /* upload xml file */
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_string);
    curl_setopt($ch, CURLOPT_URL, eo_build_url('upload_items'));
    curl_exec($ch);
    curl_close($ch);
}

/* msg is send after order is set to paid */
function invoice_creator_send_invoice($order_id){
    if(!$order_id)
        return;

    $order = new WC_Order($order_id);

    try {
        /* get client nmr */
        $invoice = eo_create_invoice_body($order);
        $msg = eo_add_header($invoice);
        $PLUGIN_DIR = dirname(dirname(dirname(__FILE__)));
        file_put_contents($PLUGIN_DIR . "/xmlfilev2.xml",
                formatXmlString($msg));

        /* create request object */
        $url = eo_build_url('invoices');

        /* send the msg to exact-online */
        eo_send_msg($msg, $url);
    } catch(Exception $e) {
        error_log('Caught Exception: ' . $e->getMessage(), 0);
        return False;
    }

    return True;

    error_log("Invoice is sent.", 0);
}

/**
 * eo_get_categories
 *
 * @param - list of params
 */
function eo_get_product_details(){
    global $wpdb;

    $query="SELECT wpp.ID, wpp.post_title,
                   r.object_id, t.term_id, term.parent, t.name
            from wp_posts wpp
            join wp_term_relationships r on wpp.ID = r.object_id
            join wp_terms t on r.term_taxonomy_id = t.term_id
            join wp_term_taxonomy term on t.term_id = term.term_id
            where wpp.post_type='product'";

    /* Select fields as defined in settings.php */
    $products = $wpdb->get_results($wpdb->prepare($query, 0));
    $result = array();
    $categories = "";
    foreach($products as $p){
        $result[$p->ID]['title'] = $p->post_title;
        $result[$p->ID]['ID'] = $p->ID;
        if($p->name != "simple")
            $result[$p->ID]['category'] .= $p->name . ",";
    }

    return $result;
}

function eo_sync_products(){
    global $wpdb;
    $cred = invc_get_credentials();
    $prod_detail = eo_get_product_details();
    $product_factory = new WC_Product_Factory();
    $xml = eo_add_header() . "<Items>";

    foreach($prod_detail as $prod){
        $product = $product_factory->get_product($prod['ID']);
        $cat = $prod['category'];
        $title = $prod['title'];
        $xml .= "<Item code='$title'>
            <Description>$title</Description>
            <IsSalesItem>". $product->is_on_sale() ."</IsSalesItem>
            <IsStockItem>0</IsStockItem>
            <IsPurchaseItem>". $product->is_purchasable() ."</IsPurchaseItem>
            <IsFractionAllowedItem>0</IsFractionAllowedItem>
            <IsMakeItem>0</IsMakeItem>
            <IsSubcontractedItem>0</IsSubcontractedItem>
            <IsTime>0</IsTime>
            <IsOnDemandItem>0</IsOnDemandItem>
            <IsWebshopItem>1</IsWebshopItem>
            <CopyRemarks>0</CopyRemarks>
            <IsSerialItem>0</IsSerialItem>
            <IsBatchItem>0</IsBatchItem>
            <Assortment code='".trim($cat, ',')."'>
                <Description>".trim($cat, ',')."</Description>
                <IsDefault>1</IsDefault>
            </Assortment>
            <Sales>
                <Price>
                    <Currency code='EUR'/>
                <Value>2</Value>
                    <VAT code='BTW'>
                    </VAT>
                </Price>
                <Unit code='eenheid' type='O'>
                    <Description>eenheid omschrijving</Description>
                </Unit>
            </Sales>
            <DateStart>". date('Y-m-d') ."</DateStart>
            <Statistical>
                <Number/>
                <Units>0</Units>
                <Quantity>0</Quantity>
            </Statistical>
            <ItemPrice type='1' leading='I'>
                <Currency code='EUR'/>
                <Value>". $product->get_price() ."</Value>
                <Unit code='eenheid' type='O'>
                    <Description>eenheid omschrijving</Description>
                </Unit>
                <UnitFactor>1</UnitFactor>
                <DateStart>". date('Y-m-d') ."</DateStart>
                <Quantity>1</Quantity>
                <Country code=''/>
            </ItemPrice>
            <ItemAccounts/>
            <ItemWarehouses>
                <ItemWarehouse>
                    <Warehouse code='1'>
                        <Description>Magazijn</Description>
                    </Warehouse>
                    <ReorderPoint>0</ReorderPoint>
                    <MaximumStock>0</MaximumStock>
                </ItemWarehouse>
            </ItemWarehouses>";
        $xml .= "</Item>";
    }

    $xml .= "</Items>";
    $xml .= "<Topics>
        <Topic code='Items'
        ts_d='0x000000001A16C752' count='".count($prod_detail)."' pagesize='250'/></Topics><Messages/></eExact>";

    $loc = "/home/richard/Documents/programming/rizit/wordpress/wp-content/plugins/woocommerce-invoice-creator/apifiles/exact-online";
    file_put_contents("$loc/sample_items.xml", formatXMLString($xml));

    /* send xml file */
    eo_send_msg(utf8_encode($xml), eo_build_url("upload_items"), $loc);
}

?>
