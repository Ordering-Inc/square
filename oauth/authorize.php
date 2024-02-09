<?php
require_once 'utils.php';
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");
$method = $_SERVER['REQUEST_METHOD'];
if ($method == "OPTIONS") {
    die();
}

if ($method == "POST") {
    // Datos
    // {
    //     "project": "ramonsquareordering",
    //     "business_id": "1",
    //     "api_key": "sadadasf23r43f43f"
    // }

    // Recibir datos
    $postBody = file_get_contents("php://input");
    $request = json_decode($postBody, true);
    $errors = [];
    if (!isset($request['project_name'])) {
        array_push($errors, 'Error: Project Name is Required');
    }
    if (!isset($request['api_key'])) {
        array_push($errors, 'Error: API Key is Required');
    }
    if (!isset($request['business_id'])) {
        array_push($errors, 'Error: Business ID is Required');
    }

    if ($errors) {
        error_response($errors, true);
        return;
    }

    // Should validate that the credentials are set in the data base
    $credentials = getPaymethodCredentials(ORDERING_URL, $request['project_name'], $request['api_key'], $request['business_id']);

    // Enviamos los datos
    requestTokenSquare($credentials, $postBody);
}
