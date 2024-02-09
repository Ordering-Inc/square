<?php
require_once 'utils.php';
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");
$method = $_SERVER['REQUEST_METHOD'];
if ($method != "OPTIONS" && $method != "POST") {
    error_response("Method Not Allowed", true, 405);
}
if ($method == "OPTIONS") {
    die();
}
if ($method == "POST") {
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
    if (!isset($request['external_id'])) {
        array_push($errors, 'Error: Business External ID is Required');
    }

    if ($errors) {
        error_response($errors, true);
        return;
    }
    $cretendials = getBussinessCredentials($request['project_name'], $request['api_key'], $request['business_id'])->result;
    $cretendials = validateToken($cretendials, $request['project_name'], $request['api_key'], $request['business_id'])->result;
    $locationUrl = SQUARE_URL . "v2/locations/{$request['external_id']}";
    $additional_headers[] = "Authorization: Bearer {$cretendials->access_token}";
    $squareStore = json_decode(request($locationUrl, 'GET', $additional_headers, null));
    $squareStore = $squareStore->location;
    $businessUpdate = json_encode([
        "name" => "{$squareStore->business_name} ({$squareStore->name})",
        "address" => $squareStore->address->address_line_1,
        "cellphone" => $squareStore->phone_number,
        "zipcode" => $squareStore->address->postal_code,
        "cellphone" => $squareStore->phone_number,
        "email" => $squareStore->business_email,
        "description" => $squareStore->description,
        "external_id" => $squareStore->id,
        "timezone" => $squareStore->timezone,
        "logo" => $squareStore->logo_url,
    ]);
    $orderingUrl = ORDERING_URL . "v400/en/" . $request['project_name'] . "/business/" . $request['business_id'];
    $ordering_headers[] = 'x-api-key: ' . $request['api_key'];
    $orderingUpdate = json_decode(request($orderingUrl, 'POST', $ordering_headers, $businessUpdate));
    $catalogUrl = SQUARE_URL . "v2/catalog/list";
    $squareCatalog = json_decode(request($catalogUrl, 'GET', $additional_headers, null));
    $ITEMS = [];
    $CATEGORIES = [];
    foreach ($squareCatalog->objects as $object) {
        switch ($object->type) {
            case 'ITEM':
                $ITEMS[$object->id] = [
                    "id" => $object->id,
                    "name" => $object->item_data->name,
                    "description" => isset($object->item_data->description)
                        ? $object->item_data->description
                        : '',
                    "category_id" => isset($object->item_data->category_id)
                        ? $object->item_data->category_id
                        : null,
                ];;
                break;
            case 'CATEGORY':
                $CATEGORIES[$object->id] = [
                    "id" => $object->id,
                    "name" => $object->category_data->name
                ];
                break;
        }
    };
    $embed_data = [];
    $debug = [
        // "locationUrl" => $locationUrl,
        // "additional_headers" => $additional_headers,
        // "squareStore" => $squareStore,
        // "orderingUpdate" => $orderingUpdate,
        "squareCatalog" => $squareCatalog,
        // "objects" => [
        //     "ITEMS" => $ITEMS,
        //     "CATEGORIES" => $CATEGORIES,
        // ]
    ];
    success_response($debug, true);
}

