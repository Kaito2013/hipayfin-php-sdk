<?php
namespace Lucas\HiPayFin\Services;

class WalletService extends AbstractService
{
    public function all()
    {
        return $this->httpGet('/wallets');
    }
}
