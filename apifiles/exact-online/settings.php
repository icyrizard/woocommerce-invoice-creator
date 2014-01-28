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
$API_FILES_LOCATION = 'apifiles/exact-online';
$FUNCTIONS_FILE = "exact_online-functions.php";
$API_NAME = "exact_online";

$TABLE_FIELDS = array(
        "api_url"  => "VARCHAR(128) DEFAULT
                      'https://start.exactonline.nl' NOT NULL",
        "division" => "VARCHAR(128) DEFAULT '' NOT NULL",
        "applicationkey" => "VARCHAR(128) DEFAULT '' NOT NULL",
        "username" => "VARCHAR(32) DEFAULT '' NOT NULL",
        "password" => "VARCHAR(64) DEFAULT '' NOT NULL",
    );

function render_settings($options){
    error_log("OPTIONS", 0);
    error_log(var_export($options, true), 0);
    $api_url = esc_html($options->api_url);
    $division = esc_html($options->division);
    $username = esc_html($options->username);
    $applicationkey = esc_html($options->applicationkey);
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
                 Division code exact-online.nl
              </td>
              <td>
                 <input type='text' name='division' size='32' value='$division' />
              </td>
           </tr>
           <tr>
              <td>
                 Application key exact-online.nl
              </td>
              <td>
                 <input type='text' name='applicationkey' size='32' value='$applicationkey' />
              </td>
           </tr>
           <tr>
              <td>
                 Gebruikersnaam exact-online.nl
              </td>
              <td>
                 <input type='text' name='username' size='32' value='$username'/>
              </td>
           </tr>
           <tr>
              <td>
                Password exact-online.nl
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
