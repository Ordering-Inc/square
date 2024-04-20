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
    // if (!isset($request['external_id'])) {
    //     array_push($errors, 'Error: Business External ID is Required');
    // }

    if ($errors) {
        error_response($errors, true);
        return;
    }
    $cretendials = getBussinessCredentials($request['project_name'], $request['api_key'], $request['business_id'])->result;
    $cretendials = validateToken($cretendials, $request['project_name'], $request['api_key'], $request['business_id'])->result;
    $cretendials->access_token = 'EAAAlyI5GX09U1Bsg_7G41rN-qgeWUuZ1T0oXv9GRfE_eTAMPGmCjwXX2yYyx5E5'; //remove and fix
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
        // "description" => $squareStore->description,
        "external_id" => $squareStore->id,
        "timezone" => $squareStore->timezone,
        "logo" => $squareStore->logo_url,
    ]);
    $orderingUrl = ORDERING_URL . "v400/en/" . $request['project_name'] . "/business/" . $request['business_id'];
    $ordering_headers[] = 'x-api-key: ' . $request['api_key'];
    $orderingUpdate = json_decode(request($orderingUrl, 'POST', $ordering_headers, $businessUpdate));
    $catalogUrl = SQUARE_URL . "v2/catalog/list";
    $squareCatalog = json_decode(request($catalogUrl, 'GET', $additional_headers, null));
    $ITEM = [];
    $CATEGORY = [];
    $ITEM_OPTION = [];
    $MODIFIER_LIST = [];
    $TYPES = [];
    #Split Items By type
    foreach ($squareCatalog->objects as $object) {
        $TYPE = $object->type;
        $$TYPE[$object->id] = $object;
    };
    $categories = [];
    foreach ($ITEM as $item) {
        $_item = [
            "id" => $item->id,
            "name" => $item->item_data->name,
            "description" => isset($item->item_data->description)
                ? $item->item_data->description
                : '',
            "options" => []
        ];
        $lowest_price = null;
        if ($item->item_data->variations) {
            foreach ($item->item_data->variations as $variation) {
                if (isset($variation->item_variation_data->price_money)) {
                    if ($lowest_price === null) {
                        $lowest_price = $variation->item_variation_data->price_money->amount;
                    }
                    if ($lowest_price > $variation->item_variation_data->price_money->amount) {
                        $lowest_price = $variation->item_variation_data->price_money->amount;
                    }
                } else {
                    $lowest_price = 0;
                }
            }
            if (count($item->item_data->variations) > 1) {
                $option = [
                    "id" => "{$item->id}-VARIATIONS",
                    "name" => "Variations",
                    "rank" => 0,
                    "max" => 1,
                    "min" => 1,
                    "type" => "variant",
                    "suboptions" => []
                ];
                foreach ($item->item_data->variations as $variation) {
                    $option["suboptions"][] = [
                        "id" => $variation->id,
                        "name" => $variation->item_variation_data->name,
                        "price" => !isset($variation->item_variation_data->price_mone) ? 0 : $variation->item_variation_data->price_money->amount - $lowest_price,
                    ];
                }
                $_item["options"][] = $option;
            }
        }
        if (isset($item->item_data->modifier_list_info)) {
            $option_rank = 1;
            foreach ($item->item_data->modifier_list_info as  $modifier_list) {
                $option = [
                    "id" => $modifier_list->modifier_list_id,
                    "name" => $MODIFIER_LIST[$modifier_list->modifier_list_id]->modifier_list_data->name,
                    "rank" => $option_rank++,
                    "max" => $modifier_list->max_selected_modifiers <= 0 ? 99 : $modifier_list->max_selected_modifiers,
                    "min" => $modifier_list->min_selected_modifiers <= 0 ? 0 : $modifier_list->min_selected_modifiers,
                    "type" => "modifier",
                    "suboptions" => []
                ];
                foreach ($MODIFIER_LIST[$option["id"]]->modifier_list_data->modifiers  as $modifier) {
                    $option["suboptions"][] = [
                        "id" => $modifier->id,
                        "name" => $modifier->modifier_data->name,
                        "price" => $modifier->modifier_data->price_money->amount
                    ];
                }
                $_item["options"][] = $option;
            }
        }
        $_item["price"] = $lowest_price ?? 0;
        if (isset($item->item_data->categories)) {
            foreach ($item->item_data->categories as $category) {
                if (isset($categories[$category->id])) {
                    $categories[$category->id]["items"][] = $_item;
                } else {
                    $categories[$category->id] = [
                        "id" => $category->id,
                        "name" => $CATEGORY[$category->id]->category_data->name,
                        "items" => [$_item],
                        "sortOrder" => 1
                    ];
                }
            }
        } else {
            if (isset($categories[$squareStore->id])) {
                $categories[$squareStore->id]["items"][] = $_item;
            } else {
                $categories[$squareStore->id] = [
                    "id" => $squareStore->id,
                    "name" => "uncategorized",
                    "items" => [$_item],
                    "sortOrder" => 0
                ];
            }
        }
    }

    #Prepare Data to Fill CSV
    $embed_data = [];
    foreach ($categories as $key => $category) {
        $category_object = (object) [
            //Business
            "busines_id" => $squareStore->id,
            //Category
            "category_id" => $category['id'],
            "category_parent_id" => null,
            "category_name" => $category['name'],
            "category_slug" => str_replace(" ", "_", strtolower($category['name'])),
            "category_description" => '',
            "category_image" => "",
            "category_rank" => $category['sortOrder'],
            "category_enabled" => true,
            //Product
            "product_id" => '',
            "product_name" => '',
            "product_price" => '',
            "product_description" => '',
            "product_slug" => '',
            "product_enabled" => '',
            "product_images" => '',
            "product_rank" => '',
            "product_maximum_per_order" => '',
            "product_calories" => '',
            //Extra
            "extra_id" => '',
            "extra_name" => '',
            "extra_rank" => '',
            //Option
            "option_id" => '',
            "option_name" => '',
            "option_image" => '',
            "option_min" => '',
            "option_max" => '',
            "option_rank" => '',
            //Suboption
            "subtoption_id" => '',
            "subtoption_name" => '',
            "subtoption_price" => '',
            "subtoption_max" => '',
            "subtoption_rank" => '',
            "subtoption_preselected" => '',
            //contitions
            "condition_option_id" => '',
            "condition_suboption_id" => '',

            "allow_suboption_quantity" => '',
            "limit_suboptions_by_max" => '',
        ];
        if ($category['items']) {
            foreach ($category['items'] as $product) {
                $product_object = clone $category_object;
                $product_object->product_id = $product['id'];
                $product_object->product_name = $product['name'];
                $product_object->product_price = $product['price'];
                $product_object->product_slug =  str_replace(" ", "_", strtolower($product['name']));
                if ($product['options']) {
                    foreach ($product['options'] as $option) {
                        $option_object = clone $product_object;
                        $option_object->extra_id = "EXTRA:" . $product['id'];
                        $option_object->extra_name = "Extra for: " . $product['name'];
                        $option_object->extra_rank = 1;
                        $option_object->option_id = $product['id'] . ":" . $option['id'];
                        $option_object->option_name = $option['name'];
                        $option_object->option_rank = $option['rank'];
                        $option_object->option_min = $option['min'];
                        $option_object->option_max = $option['max'];
                        $option_object->allow_suboption_quantity = 0;
                        if ($option['suboptions']) {
                            foreach ($option['suboptions'] as $suboption) {
                                $suboption_object = clone $option_object;
                                $suboption_object->subtoption_id = $suboption['id'];
                                $suboption_object->subtoption_name = $suboption['name'];
                                $suboption_object->subtoption_rank = 1;
                                $suboption_object->subtoption_price = $suboption['price'];
                                $suboption_object->subtoption_max = 99;
                                array_push($embed_data, $suboption_object);
                            }
                        } else {
                            array_push($embed_data, $option_object);
                        }
                    }
                } else {
                    array_push($embed_data, $product_object);
                }
            }
        } else {
            array_push($embed_data, $category_object);
        }
    }
    //FILL CSV DATASET
    $CSV = [];
    array_push($CSV, array(
        'External Business ID',
        'External Category ID',
        'External Parent Category ID',
        'Category Name',
        'Category Slug',
        'Category Description',
        'Category Image',
        'Category Rank',
        'Category Enabled',
        'External Product ID',
        'Product Name',
        'Product Price',
        'Product Description',
        'Product Slug',
        'Product Enabled',
        'Product Image',
        'Product Rank',
        'Product Max Order',
        'Product Calories',
        'External Extra ID',
        'Extra Name',
        'Extra Rank',
        'External Extra Option ID',
        'Extra Option Name',
        'Extra Option Image',
        'Extra Option Min',
        'Extra Option Max',
        'Extra Option Rank',
        'External Extra Option Suboption ID',
        'Extra Option Suboption Name',
        'Extra Option Suboption Price',
        'Extra Option Suboption Max',
        'Extra Option Suboption Rank',
        'Extra Option Suboption Preselect',
        'Extra Option Respect ID',
        'Extra Option Suboption Respect ID',
        'Extra Option Suboption Quantity',
        'Extra Option Suboption Limit Max',
    ));
    foreach ($embed_data as $csv_data) {
        array_push($CSV, [
            //Business
            $csv_data->busines_id,
            //Category
            $csv_data->category_id,
            $csv_data->category_parent_id,
            $csv_data->category_name,
            $csv_data->category_slug,
            $csv_data->category_description,
            $csv_data->category_image ?? '',
            $csv_data->category_rank,
            $csv_data->category_enabled ? 1 : 0,
            //Product
            $csv_data->product_id,
            $csv_data->product_name,
            $csv_data->product_price ? ($csv_data->product_price / 100) : 0,
            $csv_data->product_description,
            $csv_data->product_slug,
            $csv_data->product_enabled ? 1 : 0,
            $csv_data->product_images ?? '',
            $csv_data->product_rank,
            $csv_data->product_maximum_per_order,
            $csv_data->product_calories,
            //Extra
            $csv_data->extra_id,
            $csv_data->extra_name,
            $csv_data->extra_rank,
            //Option
            $csv_data->option_id,
            $csv_data->option_name,
            $csv_data->option_image,
            $csv_data->option_min,
            $csv_data->option_max,
            $csv_data->option_rank,
            //Suboption
            $csv_data->subtoption_id,
            $csv_data->subtoption_name,
            $csv_data->subtoption_price ? ($csv_data->subtoption_price / 100) : 0,
            $csv_data->subtoption_max,
            $csv_data->subtoption_rank,
            $csv_data->subtoption_preselected ? 1 : 0,
            //contitions
            $csv_data->condition_option_id,
            $csv_data->condition_suboption_id,

            $csv_data->allow_suboption_quantity ? 1 : 0,
            $csv_data->limit_suboptions_by_max ? 1 : 0,
        ]);
    }
    //FILL CSV
    $file_name_with_full_path = "../CSVS/menu_{$request['project_name']}.csv";

    file_put_contents($file_name_with_full_path, "");
    $fp = fopen($file_name_with_full_path, "w");
    foreach ($CSV as $line) {
        // though CSV stands for "comma separated value"
        // in many countries (including France) separator is ";"
        fputcsv($fp, $line, ',');
    }
    fclose($fp);

    $orderingImportUrl = ORDERING_URL . "v400/en/" . $request['project_name'] . "/importers/sync_full_menu_default_v2/jobs";
    $orderingImportMenu = json_decode(requestImport($orderingImportUrl, $ordering_headers, $file_name_with_full_path));
    $debug = [
        "types" => $TYPES,
        // "locationUrl" => $locationUrl,
        // "additional_headers" => $additional_headers,
        // "squareStore" => $squareStore,
        // "orderingUpdate" => $orderingUpdate,
        "squareCatalog" => $squareCatalog,
        "categories" => $categories,
        // "objects" => [
        //     "ITEMS" => $ITEM,
        //     "CATEGORIES" => $CATEGORY,
        //     "ITEM_OPTION" => $ITEM_OPTION,
        //     "MODIFIER_LIST" => $MODIFIER_LIST,
        // ]
        // "embed_data" => $embed_data,
        "orderingImportMenu" => $orderingImportMenu,
    ];
    success_response($debug, true);
}
