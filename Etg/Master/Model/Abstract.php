<?php

class Etg_Master_Model_Abstract extends Mage_Core_Model_Abstract
{
    protected $postParamsObject;

    protected $restClient;

    protected $logFileName = 'commerce_connect_api_call.log';

    const CC_CONFIG_PATH_URL = 'etgmaster/commerce_connect/etg_commerce_connect_api_url';
    const CC_CONFIG_PATH_TOKEN = 'etgmaster/commerce_connect/etg_commerce_connect_api_token';
    const CC_CONFIG_PATH_EMAIL = 'etgmaster/commerce_connect/etg_commerce_connect_api_email';
    const CC_CONFIG_PATH_CHANNEL_ID = 'etgmaster/commerce_connect/etg_commerce_connect_api_channel_id';
    const CC_CHECK_STOCK_URI = '/api/inventories/click_and_check';
    /**
 * ThirdPartyAbstract constructor.
 */
    public function __construct()
    {
        $this->postParamsObject = new Etg_Master_Model_PostsParams();
        $url = Mage::getStoreConfig(self::CC_CONFIG_PATH_URL);
        $this->restClient = new Zend_Rest_Client($url);
    }

    /**
     * Get Stock information from Commerce Connect API
     * @link http://staging-channel.commerceconnect.co/apidoc/1.0/inventories/click_and_check.html
     *
     * @param array | string  $skus
     * @param string $storeGroupCode
     * @param Mixed $inventorySourceName
     * @param Mixed $inventorySourceId
     * @param Mixed $siteId
     * @param int $perPage
     * @param int $page
     * @return string | mixed
     *
     * @throws Exception;
     */
    public function checkStockCommerceConnect($skus = array(), $storeGroupCode =null, $inventorySourceName = null, $inventorySourceId=null,$siteId = 0, $perPage = 50, $page = 1)
    {
        if(!empty($skus)){
            try{
                $chanelId = intval(Mage::getStoreConfig(self::CC_CONFIG_PATH_CHANNEL_ID));
                $email = Mage::getStoreConfig(self::CC_CONFIG_PATH_EMAIL);
                $token = Mage::getStoreConfig(self::CC_CONFIG_PATH_TOKEN);
                $postParam = $this->buildParams($skus , $chanelId, $siteId , $storeGroupCode, $inventorySourceName, $inventorySourceId, $perPage, $page );
                $this->restClient->getHttpClient()->setHeaders('Authorization: Token token='.$token.',email='.$email);
                $this->restClient->getHttpClient()->setHeaders('Content-Type: application/json');
                $this->restClient->getHttpClient()->setConfig(array('timeout' => 30));
                $this->restClient->getHttpClient()->setEncType(Zend_Http_Client::ENC_URLENCODED);
                $this->restClient->setNoReset(true);
                $hostName = $this->restClient->getUri()->getHost();
                Mage::log("Start Call checkStock Commerce Connect API. Host: ".$hostName.self::CC_CHECK_STOCK_URI." Params: ".$postParam." Token: ". $token." Email: ".$email,Zend_Log::DEBUG, $this->logFileName);
                $response = $this->restClient->restPost(self::CC_CHECK_STOCK_URI,$postParam);
                if($response->getStatus() == 200){
                    $result = $response->getBody();
                    Mage::log("Success Call checkStock Commerce Connect API. Data: ". $result,Zend_Log::DEBUG, $this->logFileName);
                    return $result;
                }else{
                    if(($response->getBody()!=null)){
                        $message = $response->getBody();
                        Mage::log("Error when Call checkStock Commerce Connect API. Message: ". $message,Zend_Log::DEBUG, $this->logFileName);
                        return false;
                    }
                    return false;
                }
            }catch (Exception $e){
                Mage::log("Failed Call checkStock Commerce Connect API. Message: ". $e->getMessage(),Zend_Log::DEBUG, $this->logFileName);
                throw $e;
            }
        }
        return false;
    }

    /**
     * @param array $skus
     * @param int $chanelId
     * @param int $siteId
     * @param null $storeGroupCode
     * @param null $inventorySourceName
     * @param null $inventorySourceId
     * @param int $perPage
     * @param int $page
     * @return string
     */
    public function buildParams($skus = array(), $chanelId = 2, $siteId = 0, $storeGroupCode =null, $inventorySourceName = null,$inventorySourceId=null, $perPage = 50, $page = 1)
    {
        if(!empty($skus)){
            $this->postParamsObject->setSkus($skus);
        }
        $this->postParamsObject->setChannelId($chanelId);

        if($siteId!=0){
            $this->postParamsObject->setSiteId($siteId);
        }

        if($storeGroupCode !=null)
        {
            $this->postParamsObject->setStoreGroupCode($storeGroupCode);
        }

        if($inventorySourceName!=null){
            if(is_array($inventorySourceName)){
                $this->postParamsObject->setInventorySourceName($inventorySourceName);
            }else{
                $this->postParamsObject->setInventorySourceName(array((string)$inventorySourceName));
            }

        }
        if($inventorySourceId!=null){
            $this->postParamsObject->setInventorySourceId($inventorySourceId);
        }
        $this->postParamsObject->setPerPage($perPage);
        $this->postParamsObject->setPage($page);
        $postParam = $this->postParamsObject->toJson();
        return $postParam;
    }
}