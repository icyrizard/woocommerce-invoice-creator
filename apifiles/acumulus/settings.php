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

global $API_NAME, $TABLE_FIELDS, $FUNCTIONS_FILE, $API_FILES_LOCATION;

$API_FILES_LOCATION = 'apifiles/acumulus';
$FUNCTIONS_FILE = "acumulus-functions.php";
$API_NAME = "Acumulus";
$TABLE_FIELDS = array(
        "api_url" => "VARCHAR(128) DEFAULT 'https://api.sielsystems.nl/acumulus/stable/' NOT NULL",
        "contract_code" => "VARCHAR(16) DEFAULT '' NOT NULL",
        "username" => "VARCHAR(32) DEFAULT '' NOT NULL",
        "password" => "VARCHAR(64) DEFAULT '' NOT NULL",
    );

function render_settings($options){
    $api_url = esc_html($options->api_url);
    $contract_code  = esc_html($options->contract_code);
    $username = esc_html($options->username);
    $password = esc_html($options->password);
    $textinvoice = esc_html($options->textinvoice);
    $exclude_custom_fields = esc_html($options->exclude_custom_fields);

    return "
    <form method='post' action='admin-post.php'>
        <input type='hidden' name='action' value='invoice' />
        <table>
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
                 Contract code Acumulus.nl
              </td>
              <td>
                 <input type='text' name='contract_code' size='32' value='$contract_code' />
              </td>
           </tr>
           <tr>
              <td>
                 Gebruikersnaam Acumulus.nl
              </td>
              <td>
                 <input type='text' name='username' size='32' value='$username'/>
              </td>
           </tr>
           <tr>
              <td>
                Password Acumulus.nl
              </td>
              <td>
                 <input type='text' name='password' size='16' value='$password'/>
              </td>
           </tr>
           <tr>
              <td>
                 Uitsluiting variaties producten op factuur. Meerdere waarden scheiden met ;
              </td>
              <td>
                 <input type='text' name='exclude_custom_fields' size='32' value='$exclude_custom_fields' />
              </td>
           </tr>
           <tr>
              <td>
                 Variabele tekst factuur
              </td>
              <td>
                 <textarea name='textinvoice' rows='3' cols='32 'size='128' >$textinvoice</textarea>
              </td>
           </tr>
        </table>
        <p class='submit'>
            <input type='submit' name='Submit' class='button-primary' value='Save Changes' />
        </p>
    </form>";
}

?>
