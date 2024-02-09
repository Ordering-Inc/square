<?php
require_once '../utils.php';
require_once '../oauth/utils.php';

function getBussinessCredentials($PROJECT, $API_KEY, $BUSINESS_ID) {
    $url = ORDERING_URL.'v400/en/'.$PROJECT.'/business/'.$BUSINESS_ID.'/configs/';
    $method = 'GET';
    $additional_headers[] = 'x-api-key: '.$API_KEY;
    $result = json_decode(request($url, $method, $additional_headers, null));
    if ($result->error || !$result->result) {
        return error_response("Error Get, Invalid Credentials");
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
        return error_response("Error Square Plugin is not Install");
    }
    if (!$config->value) {
        return error_response("Error Credentials, Oauth Proccress must be done");
    }

    return success_response(json_decode($config->value));
}

function validateToken ($cretentials, $PROJECT, $API_KEY, $BUSINESS_ID) {
    $now = date("Y-m-d H:i:s");
    $expires_at = date("Y-m-d H:i:s", strtotime($cretentials->expires_at));
    if ($now > $expires_at) {
        $paymethodCredendials = getPaymethodCredentials(ORDERING_URL, $PROJECT, $API_KEY, $BUSINESS_ID);
        $enviroment = $paymethodCredendials['sandbox'] ? 'data_sandbox' : 'data';
        $cretentials = refreshToken(
            SQUARE_URL,
            $paymethodCredendials[$enviroment]['client_secret'],
            $paymethodCredendials[$enviroment]['application_id'],
            $cretentials->refresh_token
        );
    }
    return success_response($cretentials);
}
