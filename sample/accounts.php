<?php
require '../vendor/autoload.php';

$login = "";
$password = "";

$client = new Splash\CallTrackingMetrics\Client($login, $password);

var_export($client->api('accounts'));