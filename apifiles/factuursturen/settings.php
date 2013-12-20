<?php

/* Alter these settings if you know what you are doing.
 * These setings will be specific per API, the names of
 * table, and currently existing TABLE_FIELDS are inserted
 * into the database.
 * Changing them after the installation will obviously
 * result in unpredictable errors.
 * Note if you need to change a field or add one, this is always
 * possible. But beware of any side effects.
 *
 * Author: Richard Torenvliet
 */
global $API_NAME, $TABLE_NAME, $TABLE_FIELDS, $FUNCTIONS_FILE, $API_FILES_LOCATION;

$API_FILES_LOCATION = 'apifiles/factuursturen';
$FUNCTIONS_FILE = "factuursturen-functions.php";
$API_NAME = "Factuursturen";

$TABLE_FIELDS = array(
        "api_key"=> "VARCHAR(42) DEFAULT '' NOT NULL",
        "api_version" => "VARCHAR(16) DEFAULT 'v1' NOT NULL",
        "api_url" => "VARCHAR(256) DEFAULT 'https://www.factuursturen.nl/api/' NOT NULL",
        "username" => "VARCHAR(32) DEFAULT '' NOT NULL",
    );

function render_settings($options){
    $api_key = esc_html($options->api_key);
    $api_version = esc_html($options->api_version);
    $api_url = esc_html($options->api_url);
    $username = esc_html($options->username);
    $textinvoice = esc_html($options->textinvoice);
    $exclude_custom_fields = esc_html($options->exclude_custom_fields);

    return "
    <form method='post' action='admin-post.php'>
        <input type='hidden' name='action' value='invoice' />
        <table>
           <tr>
              <td width='25%'>
                 API key
              </td>
              <td width='75%'>
                 <input type='text' name='api_key' size='64' value='$api_key'/>
              </td>
           </tr>
           <tr>
              <td>
                 API url
              </td>
              <td>
                 <input type='text' name='api_url' size='64' value='$api_url' />
              </td>
           </tr>
           <tr>
              <td>
                 Gebruikersnaam factuursturen.nl
              </td>
              <td>
                 <input type='text' name='username' size='32' value='$username'/>
              </td>
           </tr>
           <tr>
              <td>
                 API versie
              </td>
              <td>
                 <input type='text' name='api_version' size='16' value='$api_version'/>
              </td>
           </tr>
           <tr>
              <td>
                 Uitsluiting variaties producten op factuur. Meerdere waarden scheiden met ;
              </td>
              <td>
                 <input type='text' name='exclude_custom_fields' size='32' value='$exclude_custom_fields'/>
              </td>
           </tr>
           <tr>
              <td>
                 Variabele tekst factuur
              </td>
              <td>
                 <textarea name='textinvoice' rows='3' cols='32 'size='128' value=''>$textinvoice</textarea>
              </td>
           </tr>
        </table>
        <p class='submit'>
            <input type='submit' name='Submit' class='button-primary' value='Save Changes' />
        </p>
    </form>";
}

?>
