<?php
// echo 'oauth/callback';
header('Access-Control-Allow-Origin: *');


// require_once '../connect.php';
require_once 'utils.php';
require_once 'messages.php';

// success_response($_GET, true);
// return;

// $result_state = pg_fetch_object(find('oauth_keys', ORDERING_PROJECT_NAME, $_GET['business_id']));
// $result_state = pg_fetch_object(findKey('oauth_tokens', $_GET['state']));
$result_state = (object) [];
if (file_exists("states/{$_GET['state']}.json")) {
    $state = json_decode(file_get_contents("states/{$_GET['state']}.json"));
} else {
    displayError('INVALID_STATE_PARAMETER', '');
    return;
}

$auth_state = null;
if ($state) {
    $auth_state = $_GET['state'];
    $result_state->key_ordering = $state->api_key;
    $result_state->project = $state->project_name;
    $result_state->business = $state->business_id;
}


try {

    // Verify the state to protect against cross-site request forgery.
    if ($auth_state !== $_GET['state']) {
        displayError('INVALID_STATE_PARAMETER', '');
        return;
    }
    // $auth_state = $_GET['state'];
    // When the response_type is "code", the seller clicked Allow
    // and the authorization page returned the auth tokens.
    if ("code" === $_GET["response_type"]) {
        $authorizationCode = $_GET['code'];
        if ($result_state->key_ordering) {
            $credentials = getPaymethodCredentials(ORDERING_URL, $result_state->project, $result_state->key_ordering, $result_state->business);
            if ($credentials !== null) {
                if ($credentials !== true) {
                    if ($credentials['sandbox']) {
                        $square_url = "https://connect.squareupsandbox.com/";
                        $square_application_id = $credentials['data_sandbox']['application_id'];
                        $square_client_secret = $credentials['data_sandbox']['client_secret'];
                    } else {
                        $square_url = "https://connect.squareup.com/";
                        $square_application_id = $credentials['data']['application_id'];
                        $square_client_secret = $credentials['data']['client_secret'];
                    }
                    $response = json_decode(oauthSquare($square_url, $square_client_secret, $square_application_id, $authorizationCode));
                    if (isset($response->errors)) {
                        $code = $response->errors[0]->code;
                        // $category = $response->errors[0]->category;
                        // $detail = $response->errors[0]->detail;
                        // echo $code;
                        displayError($response->errors[0]->code, $response->errors[0]->detail);
                        exit;
                    } else {
                        $accessToken = $response->access_token;
                        $refreshToken = $response->refresh_token;
                        $expiresAt = $response->expires_at;
                        $merchantId = $response->merchant_id;
                        $additional_data = array($refreshToken, $expiresAt, $merchantId);
                        $responseEncrypt = updateOauthCredentials($response, $result_state->project, $result_state->business, $result_state->key_ordering);
                        if (!$responseEncrypt->error) {
                            writeTokensOnSuccess($accessToken, $refreshToken, $expiresAt, $merchantId, $credentials);
                        } else {
                            displayError('Permissions', 'You dont have Orderings permissions: '. $responseEncrypt->result);
                        }
                    }
                } else {
                    displayError('Permissions', 'You dont have Orderings permissions');
                }
            } else {
                displayError('Missing Square', 'You dont have Square in your projec');
            }
        } else {
            displayError('Permissions', 'You dont have Orderings permissions');
        }
    } elseif ($_GET['error']) {
        // Check to see if the seller clicked the Deny button and handle it as a special case.
        if (("access_denied" === $_GET["error"]) && ("user_denied" === $_GET["error_description"])) {
            echo 'AUTHORIZATION_DENIED';
        }
        // Display the error and description for all other errors.
        else {
            echo $_GET["error"];
        }
    } else {
        // No recognizable parameters were returned.
        echo 'UNKNOWN_PARAMETERS';
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
