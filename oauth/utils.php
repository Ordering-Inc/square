<?php
require_once '../utils.php';
// echo 'oauth/utils';

// ----------START---Request token Function---START-------------------
function requestTokenSquare($credentials, $json)
{
    $headers = getallheaders();
    $datos = json_decode($json, true);
    // echo json_encode($credentials);
    if ($credentials['sandbox'] == null) {
        return error_response("You must set the keys", true);
        // $response['status'] = "error";
        // $response['result'] = array(
        //     "error_id" => "400",
        //     "error_msg" => "You must set the keys"
        // );
    } else {
        $now = date("Y-m-d H:i:s");
        $auth_state = bin2hex(random_bytes(32));
        $permissions = urlencode(
            "PAYMENTS_READ " .
                "PAYMENTS_WRITE " .
                "ITEMS_WRITE " .
                "ITEMS_READ " .
                "CUSTOMERS_WRITE " .
                "CUSTOMERS_READ " .
                "MERCHANT_PROFILE_WRITE " .
                "MERCHANT_PROFILE_READ " .
                "ORDERS_WRITE " .
                "ORDERS_READ " .
                // "PAYMENTS_WRITE_ADDITIONAL_RECIPIENTS " .
                "INVENTORY_READ " .
                "INVENTORY_WRITE"
        );
        file_put_contents("states/{$auth_state}.json", $json);
        if ($credentials['sandbox']) {
            $square_url = "https://connect.squareupsandbox.com/";
            $square_application_id = $credentials['data_sandbox']['application_id'];
        } else {
            $square_url = "https://connect.squareup.com/";
            $square_application_id = $credentials['data']['application_id'];
        }

        $url = $square_url . 'oauth2/authorize?client_id=' . $square_application_id . '&scope=' . $permissions . '&state=' . $auth_state;
        $response = array(
            "data" => array(
                "url" => $url
            ),
            "headers" => array(
                "user-agent" => $headers['User-Agent'],
                "host" => $headers['Host']
            )
        );
        success_response($response, true);
        return;
    }
    // return $response;
}
// ----------END---Request token Function---END-------------------

// ----------START---Register token encrypt Function---START-------------------
function updateOauthCredentials($data, $ORDERING_PROJECT_NAME, $ORDERING_STORE, $API_KEY)
{
    $url = ORDERING_URL.'v400/en/'.$ORDERING_PROJECT_NAME.'/business/'.$ORDERING_STORE.'/configs/';
    $method = 'GET';
    $additional_headers[] = 'x-api-key: '.$API_KEY;
    $result = json_decode(request($url, $method, $additional_headers, null));
    if ($result->error || !$result->result) {
        return error_response("Error Get");
        // $result->url = $url;
        // return error_response($result);
    }
    $config = null;
    foreach ($result->result as $_result) {
        if ($_result->key === "square_integration_oauth_data") {
            $config = $_result;
            break;
        }
    }
    if (!$config) {
        return error_response("Error Not Exist");
    }
    $url = ORDERING_URL.'v400/en/'.$ORDERING_PROJECT_NAME.'/business/'.$ORDERING_STORE.'/configs/'.$config->id;
    $method = 'PUT';
    $data = [
        "value" => json_encode($data)
    ];
    $payload = json_encode($data);
    $result = json_decode(request($url, $method, $additional_headers, $payload));
    if ($result->error) {
        return error_response("Error Update");
    }
    return success_response('ok');
}
// ----------END---Register token encrypt Function---END-------------------

// ----------START---Oauth Function---START-------------------
function oauthSquare($SQUARE_URL, $SQUARE_CLIENT_SECRET, $SQUARE_APPLICATION_ID, $authorizationCode)
{
    $url = $SQUARE_URL . 'oauth2/token';
    $data = array(
        "client_secret" => $SQUARE_CLIENT_SECRET,
        "grant_type" => "authorization_code",
        "client_id" => $SQUARE_APPLICATION_ID,
        "code" => $authorizationCode
    );
    $method = 'POST';
    $payload = json_encode($data);
    $additional_headers[] = 'Square-Version: 2022-03-16';

    $response = request($url, $method, $additional_headers, $payload);
    return $response;
}
// ----------END---Oauth Function---END-------------------

// ----------START---Refresh token Function---START-------------------
function refreshToken($SQUARE_URL, $SQUARE_CLIENT_SECRET, $SQUARE_APPLICATION_ID, $refresh_token)
{
    $url = $SQUARE_URL . 'oauth2/token';
    $data = array(
        "client_secret" => $SQUARE_CLIENT_SECRET,
        "grant_type" => "refresh_token",
        "client_id" => $SQUARE_APPLICATION_ID,
        "refresh_token" => $refresh_token
    );
    $method = 'POST';
    $payload = json_encode($data);
    $additional_headers[] = 'Square-Version: 2022-03-16';

    $response = json_decode(request($url, $method, $additional_headers, $payload));
    return $response;
}
  // ----------END---Refresh token Function---END-------------------

// ----------START---Clean State Function---START-------------------
function cleanState($filename) {
    if (file_exists("states/{$filename}.json")) {
        unlink("states/{$filename}.json");
    }
}
// ----------END---Clean State Function---END-------------------
