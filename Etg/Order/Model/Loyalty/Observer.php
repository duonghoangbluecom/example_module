<?php
class Etg_Order_Model_Loyalty_Observer extends Bloyal_Loyalty_Model_Observer {

    /**
     * @var Bloyal_Master_Model_Abstract|false|Mage_Core_Model_Abstract
     */
    protected $_masterModel;
    /**
     * @var Bloyal_CatalogIntegrator_Helper_Data|Mage_Core_Helper_Abstract
     */
    protected $_objCatalogHelper;

    const BLOYAL_EXCEPTION_APPROVE_ERROR = 'bloyal_exception_order_approve_error';
    const BLOYAL_EXCEPTION_COMMIT_ERROR = 'bloyal_exception_order_commit_error';
    const BLOYAL_EXCEPTION_CARD_REDEEM_ERROR ='bloyal_redeem_loyaty_tender_amount_error';
    const BLOYAL_EXCEPTION_GIFT_CARD_REDEEM_ERROR = 'bloyal_redeem_loyaty_gift_cart_amount_error';
    const BLOYAL_EXCEPTION_UPDATE_INVENTORY_ERROR = 'bloyal_update_inventory_error';
    const BLOYAL_EXCEPTION_RESOLVE_CUSTOMER_ERROR = 'bloyal_resolve_customer_error';
    const BLOYAL_EXCEPTION_CART_UID_NOT_FOUND = 'bloyal_cart_uid_not_found';
    const BLOYAL_EXCEPTION_FAILURE_CONNECT = 'bloyal_api_failure_connect';

    /**
     * MTG_Order_Model_Loyalty_Observer constructor.
     */
    public function __construct()
    {
        $this->_masterModel =  Mage::getModel('bloyalMaster/abstract');
        $this->_objCatalogHelper =  Mage::helper('bloyalCatalog');
    }

    /**
     * @param $objObserver
     * @return $this
     * @throws Bloyal_Master_Helper_Exception
     */
    public function checkoutOnePageSuccessAction($objObserver){
        //ns_sales_order_place_after 1
        Mage::log("Bloyal_Loyalty_Model_Observer[34]-Bloyal checkoutOnePageSuccessAction method start ".$objObserver->getEvent()->getOrder()->getIncrementId(), null, 'PET-1232-'.gethostname().'.log');
        $objOrder = $objObserver->getEvent()->getOrder();
        $objCatalogCron = Mage::getModel('bloyalCatalog/cron');
        $strWebsiteId = Mage::app()->getWebsite()->getId();
        $arrAdminOrderDetails = Mage::getSingleton('adminhtml/session_quote')->getQuote()->getData();
        if(!isset($strWebsiteId) || $strWebsiteId == '' || $strWebsiteId == 0){
            $intStoreId = isset($arrAdminOrderDetails['store_id']) ? $arrAdminOrderDetails['store_id'] : '';
            $strWebsiteId = Mage::getModel('core/store')->load($intStoreId)->getWebsiteId();
        }

        $strOrderIncrementId = $objOrder->getIncrementId();
        $strCartUid = Mage::getSingleton('core/session')->getCartUid();
        if(isset($strCartUid) && $strCartUid != ''){
            // Approve and Commit the order
            $strStoreCode = $objCatalogCron->getStoreCode($strWebsiteId);
            $strDeviceCode = $objCatalogCron->getDeviceCode($strWebsiteId);
            $objApproveRequest = '{"CartUid":"'.$strCartUid.'","CartExternalId":"'.$strOrderIncrementId.'","ExternalId":"'.$strOrderIncrementId.'","CartSourceExternalId":"","DeviceUid":null,"StoreCode":"'.$strStoreCode.'","DeviceCode":"'.$strDeviceCode.'","Uid":"","ReferenceNumber":null}';
            try{
                $arrApproveResult = $this->_masterModel->getCurlResponse('carts/commands/approve', $objApproveRequest, 1, 'loyaltyengine');
                if($arrApproveResult == false){
                    $dataException = array('strCartUid'=>$strCartUid,'data'=>$objApproveRequest);
                    throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_FAILURE_CONNECT,0,null,Zend_Serializer::serialize($dataException));
                }
            }catch (Exception $exception){
                $dataException = array('strCartUid'=>$strCartUid,'data'=>$objApproveRequest);
                throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_APPROVE_ERROR,0,null,Zend_Serializer::serialize($dataException));
            }
            $arrApproveResult = json_decode($arrApproveResult);
            $objPayments = $this->getPaymentInfo($objOrder);
            if($arrApproveResult->status == 'success') {
                $objCommitRequest = '{"CartUid":"'.$strCartUid.'","CartExternalId":"'.$strOrderIncrementId.'","ExternalId":"'.$strOrderIncrementId.'","Payments":'. $objPayments .',"CartSourceExternalId":null,"DeviceUid":null,"StoreCode":"'.$strStoreCode.'","DeviceCode":"'.$strDeviceCode.'","Uid":"","ReferenceNumber":null}';
                try{
                    $arrCommitResult = $this->_masterModel->getCurlResponse('carts/commands/commit', $objCommitRequest, 1, 'loyaltyengine');
                }catch (Exception $e){
                    throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_COMMIT_ERROR,0,null,$objCommitRequest);
                }

                $objCommitResult = json_decode($arrCommitResult);
                if($objCommitResult->status != 'success') {
                    Mage::log('Order '.$objObserver->getEvent()->getOrder()->getIncrementId().' is not commited.', null, Mage::helper('bloyalMaster')->getLogFile());
                }
            } else {
                Mage::log('Order '.$objObserver->getEvent()->getOrder()->getIncrementId().' is not approved.', null, Mage::helper('bloyalMaster')->getLogFile());
                // should stop process when approve failed, but i don't know why they are keep moving to process
                //$dataException = array('strCartUid'=>$strCartUid,'data'=>$objApproveRequest);
                //throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_APPROVE_ERROR,0,null,Zend_Serializer::serialize($dataException));
            }
        }else{
            throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_CART_UID_NOT_FOUND,0,null);
        }

        $strPaymentCode = $objOrder->getPayment()->getMethod();
        if (Mage::getSingleton('core/session')->getTaxAmountValue())
            Mage::getSingleton('core/session')->setTaxAmountValue('');

        if (Mage::getSingleton('core/session')->getBloyalDiscountAmount())
            Mage::getSingleton('core/session')->setBloyalDiscountAmount('');

        if (($strPaymentCode == 'bloyalloyaltytender') || ($objOrder->getLoyaltyTenderValue() != null && $objOrder->getLoyaltyTenderValue() != '0.00')) {
            $strTenderCode = Mage::getStoreConfig('payment/bloyalloyaltytender/tender_code');
            $objHelper = Mage::helper('bloyalMaster');
            $strDeviceAccessKey = $objHelper->getBloyalConfig('general/access_key');
            $strStoreCode = $objCatalogCron->getStoreCode($strWebsiteId);
            $strDeviceCode = $objCatalogCron->getDeviceCode($strWebsiteId);
            $objCustomerDetails = Mage::getSingleton('customer/session')->getCustomer();
            $strEmailAddress = $objOrder->getCustomerEmail();
            $intCustomerEntityId = ($objCustomerDetails->getEntityId() ) ? $objCustomerDetails->getEntityId() : "";
            $strApiParams = 'resolvedcustomers?EmailAddress='. $strEmailAddress .'&ExternalId='. $intCustomerEntityId .'&deviceCode='. rawurlencode($strDeviceCode) .'&storeCode='.rawurlencode($strStoreCode);

            Mage::log("Bloyal_Loyalty_Model_Observer[93]-Bloyal before call ".$strApiParams." - ".$objObserver->getEvent()->getOrder()->getIncrementId(), null, 'PET-1232-'.gethostname().'.log');
            try{
                $objResolveCustomerResult = $this->_masterModel->getCurlResponse($strApiParams, '', 0, 'loyaltyengine');
            }catch (Exception $e){
                throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_RESOLVE_CUSTOMER_ERROR,0,null,$strApiParams);
            }
            Mage::log("Bloyal_Loyalty_Model_Observer[95]-Bloyal after call ".$strApiParams." - ".$objObserver->getEvent()->getOrder()->getIncrementId(), null, 'PET-1232-'.gethostname().'.log');
            $objResolveCustomerResult = json_decode($objResolveCustomerResult);
            $strCustomerUid = $objResolveCustomerResult->data->Uid;

            if ($strTenderCode && $strCustomerUid) {
                try {
                    $arrParams = array(
                        'deviceAccessKey' => $strDeviceAccessKey,
                        'storeCode' => $strStoreCode,
                        'deviceCode' => $strDeviceCode,
                        'request' => array(
                            'Amount' => $objOrder->getLoyaltyTenderValue(),
                            'CustomerUid' => $strCustomerUid,
                            'TenderCode' => $strTenderCode,
                            'Swiped' => false
                        )
                    );
                    $redeemResult = '';
                    if ($this->_masterModel->getApi('paymentengine')) {
                        try{
                            $redeemResult = $this->_masterModel->getApi('paymentengine')->CardRedeem($arrParams);
                        }catch (Exception $e){
                            if(isset($arrParams)){
                                $serializedParams = Zend_Serializer::serialize($arrParams);
                                throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_CARD_REDEEM_ERROR,0,null,$serializedParams);
                            }
                        }
                    }
                    if (!isset($redeemResult->CardRedeemResult->TransactionCode) || $redeemResult->CardRedeemResult->TransactionCode=='') {
                        Mage::throwException('Error to Redeem LoyaltyTender amount.');
                        Mage::log('Error to Redeem LoyaltyTender amount.', null, Mage::helper('bloyalMaster')->getLogFile());
                        exit;
                    }
                    if ($redeemResult->CardRedeemResult->TransactionCode) {
                        $objOrder->setLoyaltyTenderTransactionCode($redeemResult->CardRedeemResult->TransactionCode);
                        $objOrder->save();
                    }

                    if (Mage::getSingleton('core/session')->getGiftCardBalance())
                        Mage::getSingleton('core/session')->setGiftCardBalance('');

                    if (Mage::getSingleton('core/session')->getTaxAmountValue())
                        Mage::getSingleton('core/session')->setTaxAmountValue('');

                    if (Mage::getSingleton('core/session')->getLoyaltyTenderBalance())
                        Mage::getSingleton('core/session')->setLoyaltyTenderBalance('');
                    if (Mage::getSingleton('core/session')->getLoyaltyAccountNo())
                        Mage::getSingleton('core/session')->setLoyaltyAccountNo('');
                    if (Mage::getSingleton('core/session')->getMultiplePayment())
                        Mage::getSingleton('core/session')->setMultiplePayment('');
                } catch (Exception $e) {
                    if($e->getMessage())
                        Mage::log($e->getMessage(), null, Mage::helper('bloyalMaster')->getLogFile());
                    if(isset($arrParams)){
                        $serializedParams = Zend_Serializer::serialize($arrParams);
                        throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_CARD_REDEEM_ERROR,0,null,$serializedParams);
                    }
                    exit;
                }
            }
        }

        $this->updateInventoryTransaction($objOrder, 'deduct');
        Mage::log("Bloyal_Loyalty_Model_Observer[150]-Redeem the Loyalty Account Balance for ".$objOrder->getIncrementId(), null, 'PET-1232-'.gethostname().'.log');
        return $this;
    }

    /**
     * @param order $objOrder
     * @param $strType
     * @throws Bloyal_Master_Helper_Exception
     */
    public function updateInventoryTransaction($objOrder, $strType) {

        $objItemDetails = $objOrder->getAllVisibleItems();
        $strOrderIncrementId = $objOrder->getIncrementId();

        $strInventoryLocationCode = $this->_objCatalogHelper->getGeneralConfig('general/inventory_location_code');

        $objInventoryTransactionRequest = '[';
        foreach ($objItemDetails as $key => $objItem) {
            if($strType == 'add') {
                $intQty = $objItem->getQtyOrdered();
            } else {
                $intQty = '-'.$objItem->getQtyOrdered();
            }
            $objInventoryTransactionRequest .= '{
                "EntityUid": "00000000-0000-0000-0000-000000000000",
                "ChangeType": 0,
                "Entity": {
                    "MovementType": 0,
                    "InventoryLocationCode": "'.$strInventoryLocationCode.'",
                    "ProductCode": "'.$objItem->getSku().'",
                    "Quantity": '.$intQty.'
                }
            },';
        }
        $objInventoryTransactionRequest .= ']';
        $objInventoryTransactionRequest = str_replace(',]',']',$objInventoryTransactionRequest);

        try {
            $this->_masterModel->getCurlResponse('InventoryTransactions/Changes', $objInventoryTransactionRequest, 1, 'grid');
            Mage::log("Bloyal_Loyalty_Model_Observer[528]-Bloyal after call InventoryTransactions/Changes ".$objOrder->getIncrementId(), null, 'PET-1232-'.gethostname().'.log');
            Mage::log('On-hand Inventory Transaction updated for the order: '.$strOrderIncrementId, null, Mage::helper('bloyalMaster')->getLogFile());
            return;
        } catch (Exception $objException) {
            if($objInventoryTransactionRequest){
                $serializedData = Zend_Serializer::serialize($objInventoryTransactionRequest);
                throw new Bloyal_Master_Helper_Exception(self::BLOYAL_EXCEPTION_UPDATE_INVENTORY_ERROR,0,null,$serializedData);
            }
            Mage::log($objException->getMessage(), null, Mage::helper('bloyalMaster')->getLogFile());
            Mage::log('On-hand Inventory Transaction updation failed for order: '.$strOrderIncrementId, null, Mage::helper('bloyalMaster')->getLogFile());
        }
    }
}