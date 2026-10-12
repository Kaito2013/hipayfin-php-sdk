<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Config;

class ConfigTest
{
    public function testDefaultSandboxConfiguration()
    {
        $config = new Config(array(
            'api_key'     => 'test_key',
            'hmac_secret' => 'test_secret',
        ));

        if ($config->getApiKey() !== 'test_key') {
            throw new \Exception('getApiKey failed');
        }
        if ($config->getHmacSecret() !== 'test_secret') {
            throw new \Exception('getHmacSecret failed');
        }
        if ($config->getEnvironment() !== Config::ENV_SANDBOX) {
            throw new \Exception('default environment must be sandbox');
        }
        if ($config->getBaseUrl() !== 'https://api-sandbox.hipayfin.com/api/v1') {
            throw new \Exception('sandbox base url incorrect');
        }
        if ($config->getTimeout() !== 30) {
            throw new \Exception('default timeout must be 30');
        }
        return true;
    }

    public function testProductionConfiguration()
    {
        $config = new Config(array(
            'api_key'     => 'prod_key',
            'hmac_secret' => 'prod_secret',
            'environment' => Config::ENV_PRODUCTION,
            'timeout'     => 45,
        ));

        if ($config->getBaseUrl() !== 'https://api.hipayfin.com/api/v1') {
            throw new \Exception('production base url incorrect');
        }
        if ($config->getTimeout() !== 45) {
            throw new \Exception('timeout override failed');
        }
        return true;
    }
}
