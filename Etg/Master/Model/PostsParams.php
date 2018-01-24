<?php

/**
 * Class Etg_Master_Model_PostsParams
 */
class Etg_Master_Model_PostsParams extends Varien_Object
{
    const CC_SKUS = 'skus';

    const CC_CHANNEL_ID = 'channel_id';

    const CC_SITE_ID ='site_id';

    const CC_STORE_GROUP_CODE = 'store_group_code';

    const CC_INVENTORY_SOURCE_NAME = 'inventory_source_names';

    const CC_INVENTORY_SOURCE_ID ='inventory_source_id';

    const CC_PER_PAGE = 'per_page';

    const CC_PAGE ='page';

    /**
     * @var
     */
    public $sku ;
    /**
     * @var int
     */
    public $channel_id = 0;
    /**
     * @var int
     */
    public $site_id = 0;
    /**
     * @var null
     */
    public $store_group_code =null;
    /**
     * @var null
     */
    public $inventory_source_name = null;
    /**
     * @var null
     */
    public $inventory_source_id=null;
    /**
     * @var int
     */
    public $per_page = 50;
    /**
     * @var int
     */
    public $page = 1;

    /**
     * @return mixed
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @param $sku
     * @return $this
     */
    public function setSku($sku)
    {
        $this->setData(self::CC_SKUS, $sku);
        return $this;
    }

    /**
     * @return int
     */
    public function getChannelId()
    {
        return $this->channel_id;
    }

    /**
     * @param $channel_id
     * @return $this
     */
    public function setChannelId($channel_id)
    {
        $this->setData(self::CC_CHANNEL_ID,$channel_id);
        return $this;
    }

    /**
     * @return int
     */
    public function getSiteId()
    {
        return $this->site_id;
    }

    /**
     * @param $site_id
     * @return $this
     */
    public function setSiteId($site_id)
    {
        $this->setData(self::CC_SITE_ID,$site_id);
        return $this;
    }

    /**
     * @return null
     */
    public function getStoreGroupCode()
    {
        return $this->store_group_code;
    }

    /**
     * @param $store_group_code
     * @return $this
     */
    public function setStoreGroupCode($store_group_code)
    {
        $this->setData(self::CC_STORE_GROUP_CODE,$store_group_code);
        return $this;
    }

    /**
     * @return null
     */
    public function getInventorySourceName()
    {
        return $this->inventory_source_name;
    }

    /**
     * @param $inventory_source_name
     * @return $this
     */
    public function setInventorySourceName($inventory_source_name)
    {
        $this->setData(self::CC_INVENTORY_SOURCE_NAME,$inventory_source_name);
        return $this;
    }

    /**
     * @return null
     */
    public function getInventorySourceId()
    {
        return $this->inventory_source_id;
    }

    /**
     * @param $inventory_source_id
     * @return $this
     */
    public function setInventorySourceId($inventory_source_id)
    {
        $this->setData(self::CC_INVENTORY_SOURCE_ID,$inventory_source_id);
        return $this;
    }

    /**
     * @return int
     */
    public function getPerPage()
    {
        return $this->per_page;
    }

    /**
     * @param $per_page
     * @return $this
     */
    public function setPerPage($per_page)
    {
        $this->setData(self::CC_PER_PAGE,$per_page);
        return $this;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param $page
     * @return $this
     */
    public function setPage($page)
    {
        $this->setData(self::CC_PAGE,$page);
        return $this;
    }

    /**
     * @return mixed
     */



}