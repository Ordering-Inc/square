<link rel="stylesheet" href="public/style.css" type="text/css">
<meta name="viewport" content="width=device-width">
<script>
    function sendData(data) {
        var d = data.split("||");
        var MyArray = {
            "access_token": d[0],
            "refresh_token": d[1],
            "expire_at": d[2],
            "merchant_id": d[3],
            "paymethod_credentials": {
                "data": {
                    "application_id": d[4],
                    "location_id": d[5],
                    "client_secret": d[6]
                },
                "data_sandbox": {
                    "application_id": d[7],
                    "location_id": d[8],
                    "client_secret": d[9]
                },
                "sandbox": (d[10]) ? true : false
            }
        };
        // var MyArray = JSON.parse(data);
        // var MyArray = new Array(data);
        window.opener.postMessage(MyArray, "*");
    }
</script>
<?php
// The following functions are display helpers and should only be used in Sandbox applications.

// Write token information to the page.
// Do not do this in production. In production, replace this function with one that stores the OAuth tokens securely.
function writeTokensOnSuccess($accessToken, $refreshToken, $expiresAt, $merchantId, $paymethod_credentials)
{
    $data = $accessToken . "||" .
        $refreshToken . "||" .
        $expiresAt . "||" .
        $merchantId . "||" .
        $paymethod_credentials['data']['application_id'] . "||" .
        $paymethod_credentials['data']['location_id'] . "||" .
        $paymethod_credentials['data']['client_secret'] . "||" .
        $paymethod_credentials['data_sandbox']['application_id'] . "||" .
        $paymethod_credentials['data_sandbox']['location_id'] . "||" .
        $paymethod_credentials['data_sandbox']['client_secret'] . "||" .
        $paymethod_credentials['sandbox'];
    $result = "<div class='wrapper'>
                    <div class='messages'>
                        <h1>Authorization Succeeded</h1>
                        <div style='color:rgba(204, 0, 35, 1)'><strong>Caution:</strong> NEVER store or share OAuth access tokens or refresh tokens in clear text.</div>
                        <br/>
                        <div><input class='btn' type='Button' value='Continue' onclick=\"sendData('" . $data . "')\"></div>
                    </div>
                </div>";
    echo $result;
}

// Display error message if the state doesn't match the state originally passed to the authorization page.
function displayStateError()
{
    $result = "<div class='wrapper'>
                    <div class='messages'>
                        <h1>Authorization failed</h1>
                        <div>Invalid state parameter.</div>
                    </div>
                </div>";
    echo $result;
}

// Disply error message.
function displayError($error, $error_description)
{
    $result = "<div class='wrapper'>
                    <div class='messages'>
                        <h1>Authorization failed</h1>
                        <div>Error: " . $error . "</div>
                        <div>Error Details: " . $error_description . "</div>
                    </div>
                </div>";
    echo $result;
}
?>
