<?php
// Ordering Credentials
define("ORDERING_URL", "https://apiv4.ordering.co/");
// define("ORDERING_PROJECT_NAME", "ramonsquareordering");
// define("ORDERING_API_KEY", "meuDUKEjIYnxjeh_3bo8uq5vsKxUo7mdRMcbaLNW3_8yDnUDaa5lUeR2_LN7JzHG7");
// define("ORDERING_STORE", 41);

// Square Credentials
define("SQUARE_ENVIRONMENT", "sandbox");
// define("SQUARE_APPLICATION_ID", "sandbox-sq0idb-rMLAce87hOfpGvokZCygEw");
// define("SQUARE_CLIENT_SECRET", "sandbox-sq0csb-kt2PiepjDCOllAL_IihE0cxP0whPm04qosuZHtZ14b0");
// define("SQUARE_ACCESS_TOKEN", "EAAAEM3DyHbTXbUzdHGEYItzoPvP2SZpQHHsaApJeJfyQoZ9VbygBKOD_KZchZau");
// define("SQUARE_LOCATION_ID", "L1NGAY5M6KJRX");

if (SQUARE_ENVIRONMENT == "sandbox") {
    define("SQUARE_URL", "https://connect.squareupsandbox.com/");
} else if (SQUARE_ENVIRONMENT == "production") {
    define("SQUARE_URL", "https://connect.squareup.com/");
}
