<?php
require '../vendor/autoload.php';

$login    = "ACCESS TOKEN";
$password = "ACCESS SECRET";

$client = new Splash\CallTrackingMetrics\Client($login, $password);

try {
    var_export($client->api('accounts'));
} catch (\Splash\CallTrackingMetrics\AuthException $e) {
    echo "!! Invalid authentication credentials!";
}

echo "\n";