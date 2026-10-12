<?php
require_once __DIR__ . '/../autoload.php';

use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Config;

$client = new Client(array(
    'api_key'        => 'hk_live_VbdBEm3for1GBYzuke6kYl0BHpx9R3afH_yK',
    'hmac_secret'    => '35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de',
    'webhook_secret' => '35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de', // default fallback for demo
    'environment'    => Config::ENV_SANDBOX,
));
