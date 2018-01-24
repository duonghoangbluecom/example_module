<?php

class Etg_Order_Model_Observer extends Bloyal_Pay_Model_Observer {

    /**
     * @var Bloyal_Master_Model_Abstract|false|Mage_Core_Model_Abstract
     */
    protected $_masterModel;

    /**
     * Etg_Order_Model_Observer constructor.
     */
    public function __construct()
    {
        $this->_masterModel = Mage::getModel('bloyalMaster/abstract');
    }


    /**
     * @param $observer
     * @return $this
     * @throws Bloyal_Master_Helper_Exception
     */
    public function hookIntoCheckoutOnePageSuccessAction($observer){
        // ns_sales_order_place_after 2
        $objOrder = $observer->getEvent()->getOrder();
        $strPaymentCode = $objOrder->getPayment()->getMethod();

        if (Mage::getSingleton('core/session')->getPaymentSucess())
            Mage::getSingleton('core/session')->setPaymentSucess('');

        if (Mage::getSingleton('core/session')->getTaxAmountValue())
            Mage::getSingleton('core/session')->setTaxAmountValue('');

        if (Mage::getSingleton('core/session')->getBloyalDiscountAmount())
            Mage::getSingleton('core/session')->setBloyalDiscountAmount('');

        //unset discount coupon codes
        if (Mage::getSingleton('core/session')->getDiscountCouponCode())
            Mage::getSingleton('core/session')->setDiscountCouponCode('');

        if (($strPaymentCode == 'bloyalgiftcard') || ($objOrder->getGiftCardNo())) {

            $strTenderCode = Mage::getStoreConfig('payment/bloyalgiftcard/gift_code');
            $objHelper = Mage::helper('bloyalMaster');
            $strDeviceAccessKey = $objHelper->getBloyalConfig('general/access_key');

            if ($strTenderCode) {
                try {
                    $objCatalogCron = Mage::getModel('bloyalCatalog/cron');
                    $strWebsiteId = Mage::app()->getWebsite()->getId();
                    $strStoreCode = $objCatalogCron->getStoreCode($strWebsiteId);
                    $strDeviceCode = $objCatalogCron->getDeviceCode($strWebsiteId);

                    $arrParams = array(
                        'deviceAccessKey' => $strDeviceAccessKey,
                        'storeCode' => $strStoreCode,
                        'deviceCode' => $strDeviceCode,
                        'request' => array(
                            'Amount' => $objOrder->getGiftCardValue(),
                            'CardNumber' => $objOrder->getGiftCardNo(),
                            'TenderCode' => $strTenderCode,
                            'Swiped' => 'false',
                            'TransactionExternalId' => $objOrder->getIncrementId()
                        )
                    );
                    $redeemResult = '';

                    if ($this->_masterModel->getApi('paymentengine')) {
                        $redeemResult = $this->_masterModel->getApi('paymentengine')->CardRedeem($arrParams);
                    }
                    if (!isset($redeemResult->CardRedeemResult->TransactionCode) || $redeemResult->CardRedeemResult->TransactionCode=='') {
                        if(isset($arrParams)){
                            $serializedRedeemData = Zend_Serializer::serialize($arrParams);
                            throw new Bloyal_Master_Helper_Exception(Etg_Order_Model_Loyalty_Observer::BLOYAL_EXCEPTION_GIFT_CARD_REDEEM_ERROR,0,null,$serializedRedeemData);
                        }
                        Mage::throwException('Error to Redeem Giftcard ammount.');
                        Mage::log('Error to Redeem Giftcard ammount.', null, Mage::helper('bloyalMaster')->getLogFile());
                        exit;
                    }
                    if ($redeemResult->CardRedeemResult->TransactionCode) {
                        $objOrder->setGiftCardTransactionCode($redeemResult->CardRedeemResult->TransactionCode);
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
                    if(isset($arrParams)){
                        $serializedRedeemData = Zend_Serializer::serialize($arrParams);
                        throw new Bloyal_Master_Helper_Exception(Etg_Order_Model_Loyalty_Observer::BLOYAL_EXCEPTION_GIFT_CARD_REDEEM_ERROR,0,null,$serializedRedeemData);
                    }
                    Mage::throwException($e->getMessage());
                    Mage::log($e->getMessage(), null, Mage::helper('bloyalMaster')->getLogFile());
                    exit;
                }
                return $this;
            }
        }
    }
}