<?php

require_once 'constants.php';
// echo 'main utils';
function request($url, $method, $additional_headers, $data = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (in_array($method, ['PUT', 'POST'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, 1);
    }
    $additional_headers[] = 'Accept: application/json';
    $additional_headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $additional_headers);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function requestImport($url, $ordering_headers, $file_name_with_full_path)
{
    if (function_exists('curl_file_create')) {
        $curlFile = curl_file_create($file_name_with_full_path);
    } else {
        $curlFile = '@' . realpath($file_name_with_full_path);
    }
    $post = array('import_options' => json_encode(array("separator" => ",", "start_line" => "2")), 'file' => $curlFile);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $ordering_headers);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

#Responses
function error_response($data, $http = false, $code = 400)
{
    $response = [
        "error" => true,
        "result" => $data
    ];
    if ($http) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($response);
    }
    return (object) $response;
}

function success_response($data, $http = false, $code = 200)
{
    $response = [
        "error" => false,
        "result" => $data
    ];
    if ($http) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($response);
    }
    return (object) $response;
}


function getSquareCredentials($ORDERING_URL, $ORDERING_PROJECT_NAME, $ORDERING_API_KEY)
{
    $url = $ORDERING_URL . 'v400/en/' . $ORDERING_PROJECT_NAME . '/config_categories?where=[{"attribute":"key","value":"square"}]';
    $method = 'GET';
    $additional_headers[] = 'x-api-key: ' . $ORDERING_API_KEY;
    $result = json_decode(request($url, $method, $additional_headers));
    $data = null;
    if ($result->error) {
        $data = $result->error;
    } elseif (!empty($result->result)) {
        if (!empty($result->result[0]->configs)) {
            $data = [
                'square_sandbox' => null,
                'square_access_token' => null,
                'square_access_token_sandbox' => null
            ];
            foreach ($result->result[0]->configs as $configs) {
                if ($configs->key == 'square_sandbox') {
                    // $data['square_sandbox'] = ($configs->value === 1) ? true : false;
                    $data['square_sandbox'] = $configs->value;
                } elseif ($configs->key == 'square_access_token') {
                    $data['square_access_token'] = $configs->value;
                } elseif ($configs->key == 'square_access_token_sandbox') {
                    $data['square_access_token_sandbox'] = $configs->value;
                }
            }
        }
    }
    return $data;
}

function getPaymethodCredentials($ORDERING_URL, $ORDERING_PROJECT_NAME, $ORDERING_API_KEY, $ORDERING_STORE)
{
    $url = $ORDERING_URL . 'v400/en/' . $ORDERING_PROJECT_NAME . '/paymethods?where=[{"attribute":"gateway","value":"square"}]';
    $method = 'GET';
    $additional_headers[] = 'x-api-key: ' . $ORDERING_API_KEY;
    $result = json_decode(request($url, $method, $additional_headers));
    $data = null;
    if ($result->error) {
        $data = $result->error;
    } elseif (!empty($result->result)) {

        $data = [
            "data" => [
                "application_id" => null,
                "location_id" => null,
                "client_secret" => null
            ],
            "data_sandbox" => [
                "application_id" => null,
                "location_id" => null,
                "client_secret" => null
            ],
            "sandbox" => null
        ];

        $url = $ORDERING_URL . 'v400/en/' . $ORDERING_PROJECT_NAME . '/business/' . $ORDERING_STORE . '/paymethods';
        $result2 = json_decode(request($url, $method, $additional_headers));
        foreach ($result2->result as $paymethod) {
            if ($paymethod->paymethod_id == $result->result[0]->id) {
                // production
                $data['data']['application_id'] = (isset($paymethod->data->application_id)) ? $paymethod->data->application_id : null;
                $data['data']['location_id'] = (isset($paymethod->data->location_id)) ? $paymethod->data->location_id : null;
                $data['data']['client_secret'] = (isset($paymethod->data->client_secret)) ? $paymethod->data->client_secret : null;

                // sandbox
                $data['data_sandbox']['application_id'] = (isset($paymethod->data_sandbox->application_id)) ? $paymethod->data_sandbox->application_id : null;
                $data['data_sandbox']['location_id'] = (isset($paymethod->data_sandbox->location_id)) ? $paymethod->data_sandbox->location_id : null;
                $data['data_sandbox']['client_secret'] = (isset($paymethod->data_sandbox->client_secret)) ? $paymethod->data_sandbox->client_secret : null;

                $data['sandbox'] = (isset($paymethod->sandbox)) ? $paymethod->sandbox : true;
                break;
            }
        }
    }
    return $data;
}
