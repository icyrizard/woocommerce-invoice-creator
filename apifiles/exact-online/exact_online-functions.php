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
    $url_list = array(
        "invoices" => "invoices/invoice_add.php",
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

/* send function via curl
 * @param xml_string - xml body,
 *               url - url of exact-online.
 * */
function eo_send_msg($xml_string, $url, $loc){
    $xml_string = utf8_encode($xml_string);
    $verbose = fopen('php://temp', 'rw+');

    $baseurl = "https://start.exactonline.nl";
    $username = "info@sponiza.nl";
    $password = "Nhu22VaQ";
    $applicationkey = "07cae1bf-27a1-4c6a-a4a3-572ae7866bc6"; /* The application key with or without curly braces */
    $division = "545462";  /* Check the result of the first call to XMLDivisions.aspx to see all available divisions */
    $cookiefile = "$loc/cookie.txt";
    #$crtbundlefile = "cacert.pem"; /* this can be downloaded from http://curl.haxx.se/docs/caextract.html */
    /* Logging in */
    $header[1] = "Cache-Control: private";
    $header[2] = "Connection: Keep-Alive";
    $url= "$baseurl/docs/XMLUpload.aspx?Topic=Items&output=1&ApplicationKey=$applicationkey";
    $clearses= "$baseurl/docs/ClearSession.aspx?Division=$division&Remember=3";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiefile);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array("_UserName_"=>"$username", "_Password_"=>"$password"));
    curl_setopt($ch, CURLOPT_URL, $clearses);
    curl_exec($ch);
    $div = "$baseurl/docs/XMLDivisions.aspx";
    curl_setopt($ch, CURLOPT_URL, $div);
    curl_exec($ch);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_string);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_exec($ch);

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    elog($verboseLog);
    //"Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";

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
        elog($msg);

        $PLUGIN_DIR = dirname(dirname(dirname(__FILE__)));
        file_put_contents($PLUGIN_DIR . "/xmlfilev2.xml", formatXmlString($msg));

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

function eo_get_categories($products){
    $result = array();
    $categories = "";
    foreach($products as $p){
        elog("pname!!!:");
        elog($p->name);
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

    $query="SELECT wpp.ID, wpp.post_title,
                   r.object_id, t.term_id, term.parent, t.name
            from wp_posts wpp
            join wp_term_relationships r on wpp.ID = r.object_id
            join wp_terms t on r.term_taxonomy_id = t.term_id
            join wp_term_taxonomy term on t.term_id = term.term_id
            where wpp.post_type='product'";
    /* Select fields as defined in settings.php */
    $products = $wpdb->get_results(
        $wpdb->prepare("
            $query", 0)
    );

    //elog($products);
    $prod_category = eo_get_categories($products);
    elog($prod_category);

    $product_factory = new WC_Product_Factory();
    $xml = eo_add_header() . "<Items>";

    //$product = $product_factory->get_product($prod->ID);
    //elog("title");
    //elog($prod->post_title);
    //elog("data");
    //elog($product->get_post_data());
    //elog("price");
    //elog($product->get_price());
    //elog("total stock:");
    //elog($product->get_total_stock());
    //elog("total qty:");
    //elog($product->get_stock_quantity());
    //elog("purchasable:");
    //elog($product->is_purchasable());
    foreach($prod_category as $prod){
        //elog($prod_category[$prod->ID]);
        elog($prod['ID']);
        $product = $product_factory->get_product($prod['ID']);
        elog($product);

        elog("category");
        $cat = $prod['category'];
        elog($cat);
        $title = $prod['title'];
        elog("tax_class");
        elog($product->get_tax_class());
        elog('price');
        elog($product->get_price());

        $xml .= "<Item code='$title'>";

        $xml .= "
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
                    <VAT code='BTW' type='I' charged='0' vattransactiontype='B' blocked='0'>
                        <Description>btw inclusief omschrijving</Description>
                        <Percentage>0.21</Percentage>
                        <EUSalesListing>N</EUSalesListing>
                        <Intrastat>0</Intrastat>
                        <VatDocType>P</VatDocType>
                        <CalculationBasis>1</CalculationBasis>
                        <GLToPay code='rekening1' type='24' balanceSide='C' balanceType='B'>
                            <Description>rekenening omscrhijving</Description>
                        </GLToPay>
                        <GLToClaim code='rekening1' type='24' balanceSide='C' balanceType='B'>
                            <Description>rekenening omscrhijving</Description>
                        </GLToClaim>
                        <VATPercentages>
                            <VATPercentage percentage='0.21' LineNumber='1'/>
                        </VATPercentages>
                    </VAT>
                </Price>
                <Unit code='eenheid' type='O'>
                    <Description>eenheid omschrijving</Description>
                </Unit>
            </Sales>
            <DateStart>" . date('Y-m-d') ."</DateStart>
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
    $xml .= '<Topics>
        <Topic code="Items"
        ts_d="0x000000001A16C752" count="2" pagesize="250"/>
        </Topics>
        <Messages/></eExact>';

    $loc = "/home/richard/Documents/programming/rizit/wordpress/wp-content/plugins/woocommerce-invoice-creator/apifiles/exact-online";
    file_put_contents("$loc/sample_items.xml", formatXMLString($xml));

    eo_send_msg(utf8_encode($xml), '', $loc);

    //$loop = new WP_Query( $args );
    //
    //if ($loop->have_posts() ) {
    //    while ( $loop->have_posts() ) : $loop->the_post();
    //        elog($product);
    //    endwhile;
    //} else {
    //    echo __( 'No products found' );
    //}


    wp_reset_postdata();
}

?>
