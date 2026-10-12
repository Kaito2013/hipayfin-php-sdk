<?php
require_once __DIR__ . '/../autoload.php';

use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Config;

$client = new Client(array(
    'api_key'        => 'hk_live_e6CUp24YOaoMHiy7l57TOGv8ptJPgPPurgSCtWPS6zg',
    'hmac_secret'    => '1ba1a939f2f08daf0e4ad683e3560eb5984609f92fca17d2fa07f28fb8ae9338',
    'webhook_secret' => '1ba1a939f2f08daf0e4ad683e3560eb5984609f92fca17d2fa07f28fb8ae9338', // default fallback for demo
    'environment'    => Config::ENV_SANDBOX,
));
