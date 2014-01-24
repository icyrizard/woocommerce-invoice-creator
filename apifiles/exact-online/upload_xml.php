<?php
$baseurl = "https://start.exactonline.nl";
$username = "info@sponiza.nl";
$password = "Nhu22VaQ";
$applicationkey = "07cae1bf-27a1-4c6a-a4a3-572ae7866bc6"; /* The application key with or without curly braces */
$division = "545462";  /* Check the result of the first call to XMLDivisions.aspx to see all available divisions */
$cookiefile = "cookie.txt";
#$crtbundlefile = "cacert.pem"; /* this can be downloaded from http://curl.haxx.se/docs/caextract.html */
/* Logging in */
$header[1] = "Cache-Control: private";
$header[2] = "Connection: Keep-Alive";

$filename = "items.xml";
#/docs/Public.aspx?ReturnUrl=%2fdocs%2fXMLUpload.aspx%3fTopic%3dItems%26output%3d1%26ApplicationKey%3d"
$url= "$baseurl/docs/XMLUpload.aspx?Topic=Items&output=1&ApplicationKey=$applicationkey";

#./docs/XMLUpload.aspx?Topic=GLTransactions&ApplicationKey={37197c02-8345-4fd4-87f2-f008b3e37125}&Params_Journal=90

echo $url;


/* send function via curl
 * @param xml_string - xml body,
 *               url - url of acumulus.
 * */
function eo_send_msg($xml_string, $url){
    global $username, $password;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "xmlstring=$xml_string");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array("_UserName_"=>"$username", "_Password_"=>"$password"));
    $result = curl_exec($ch);
    echo $result;
    ##if(curl_exec($ch) === false){
    ##    throw new Exception("Error in msg sending. Make sure host is available.");
    ##}

    curl_close($ch);
}

#$url="$baseurl/docs/Public.aspx?ReturnUrl=%2fdocs%2fXMLUpload.aspx%3fTopic%3dItems%26output%3d1%26ApplicationKey%3d%7b07cae1bf-27a1-4c6a-a4a3-572ae7866bc6%7d";

if (file_exists($filename)) {
    /* Send the xml along with the request */
    $fp = fopen($filename, "r");
    $xml = fread($fp, filesize($filename));

    eo_send_msg(utf8_encode($xml), $url);
    //curl_setopt($ch, CURLOPT_POSTFIELDS, utf8_encode($xml));
    //$result = curl_exec($ch);

} else {
    $result = $filename." file is not found!";
}


?>
