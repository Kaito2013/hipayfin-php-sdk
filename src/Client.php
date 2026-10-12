<?php
namespace Lucas\HiPayFin;

use Lucas\HiPayFin\Http\HttpClientInterface;
use Lucas\HiPayFin\Http\CurlHttpClient;
use Lucas\HiPayFin\Services\PayinService;
use Lucas\HiPayFin\Services\PayoutService;
use Lucas\HiPayFin\Services\OrderService;
use Lucas\HiPayFin\Services\WalletService;
use Lucas\HiPayFin\Services\WebhookService;

/**
 * HiPayFin Merchant Payment Gateway Client
 *
 * @property-read PayinService $payin
 * @property-read PayoutService $payout
 * @property-read OrderService $orders
 * @property-read WalletService $wallets
 * @property-read WebhookService $webhook
 */
class Client
{
    /** @var Config */
    protected $config;

    /** @var HttpClientInterface */
    protected $httpClient;

    /** @var array Cache for instantiated services */
    protected $services = array();

    /**
     * @param Config|array $configOrOptions
     * @param HttpClientInterface|null $httpClient
     */
    public function __construct($configOrOptions, HttpClientInterface $httpClient = null)
    {
        if ($configOrOptions instanceof Config) {
            $this->config = $configOrOptions;
        } else {
            $this->config = new Config((array)$configOrOptions);
        }

        $this->httpClient = ($httpClient !== null) ? $httpClient : new CurlHttpClient($this->config);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getHttpClient()
    {
        return $this->httpClient;
    }

    public function __get($name)
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }

        switch ($name) {
            case 'payin':
                return $this->services['payin'] = new PayinService($this->httpClient);
            case 'payout':
                return $this->services['payout'] = new PayoutService($this->httpClient);
            case 'orders':
                return $this->services['orders'] = new OrderService($this->httpClient);
            case 'wallets':
                return $this->services['wallets'] = new WalletService($this->httpClient);
            case 'webhook':
                return $this->services['webhook'] = new WebhookService($this->config);
            default:
                throw new \InvalidArgumentException("Undefined property or service: {$name}");
        }
    }
}
