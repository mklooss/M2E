<?php

/*
 * @copyright  Copyright (c) 2013 by  ESS-UA.
 */

class Ess_M2ePro_Model_Ebay_Template_Return extends Ess_M2ePro_Model_Component_Abstract
{
    // ########################################

    /**
     * @var Ess_M2ePro_Model_Marketplace
     */
    private $marketplaceModel = NULL;

    // ########################################

    public function _construct()
    {
        parent::_construct();
        $this->_init('M2ePro/Ebay_Template_Return');
    }

    // ########################################

    public function isLocked()
    {
        if (parent::isLocked()) {
            return true;
        }

        return (bool)Mage::getModel('M2ePro/Ebay_Listing')
                            ->getCollection()
                            ->addFieldToFilter('template_return_mode',
                                                Ess_M2ePro_Model_Ebay_Template_Manager::MODE_TEMPLATE)
                            ->addFieldToFilter('template_return_id', $this->getId())
                            ->getSize() ||
               (bool)Mage::getModel('M2ePro/Ebay_Listing_Product')
                            ->getCollection()
                            ->addFieldToFilter('template_return_mode',
                                                Ess_M2ePro_Model_Ebay_Template_Manager::MODE_TEMPLATE)
                            ->addFieldToFilter('template_return_id', $this->getId())
                            ->getSize();
    }

    public function deleteInstance()
    {
        $temp = parent::deleteInstance();
        $temp && $this->marketplaceModel = NULL;
        return $temp;
    }

    // #######################################

    /**
     * @return Ess_M2ePro_Model_Marketplace
     */
    public function getMarketplace()
    {
        if (is_null($this->marketplaceModel)) {
            $this->marketplaceModel = Mage::helper('M2ePro/Component_Ebay')->getCachedObject(
                'Marketplace', $this->getMarketplaceId()
            );
        }

        return $this->marketplaceModel;
    }

    /**
     * @param Ess_M2ePro_Model_Marketplace $instance
     */
    public function setMarketplace(Ess_M2ePro_Model_Marketplace $instance)
    {
         $this->marketplaceModel = $instance;
    }

    // #######################################

    public function getTitle()
    {
        return $this->getData('title');
    }

    public function isCustomTemplate()
    {
        return (bool)$this->getData('is_custom_template');
    }

    public function getMarketplaceId()
    {
        return (int)$this->getData('marketplace_id');
    }

    //--------------------------------------

    public function getCreateDate()
    {
        return $this->getData('create_date');
    }

    public function getUpdateDate()
    {
        return $this->getData('update_date');
    }

    // #######################################

    public function getAccepted()
    {
        return $this->getData('accepted');
    }

    public function getOption()
    {
        return $this->getData('option');
    }

    public function getWithin()
    {
        return $this->getData('within');
    }

    public function getShippingCost()
    {
        return $this->getData('shipping_cost');
    }

    public function getRestockingFee()
    {
        return $this->getData('restocking_fee');
    }

    public function getDescription()
    {
        return $this->getData('description');
    }

    // #######################################

    public function getTrackingAttributes()
    {
        return array();
    }

    public function getUsedAttributes()
    {
        return array();
    }

    // #######################################

    public function getNick()
    {
        return Ess_M2ePro_Model_Ebay_Template_Manager::TEMPLATE_RETURN;
    }

    // #######################################

    public function getDefaultSettingsSimpleMode()
    {
        return array(
            'accepted'       => 'ReturnsNotAccepted',
            'option'         => '',
            'within'         => '',
            'shipping_cost'  => '',
            'restocking_fee' => '',
            'description'    => ''
        );
    }

    public function getDefaultSettingsAdvancedMode()
    {
        return $this->getDefaultSettingsSimpleMode();
    }

    // #######################################

    public function save()
    {
        Mage::helper('M2ePro/Data_Cache')->removeTagValues('ebay_template_return');
        return parent::save();
    }

    public function delete()
    {
        Mage::helper('M2ePro/Data_Cache')->removeTagValues('ebay_template_return');
        return parent::delete();
    }

    // #######################################

    public function getAffectedListingProducts($asObjects = false)
    {
        if (is_null($this->getId())) {
            throw new LogicException('Method require loaded instance first');
        }

        $template = Ess_M2ePro_Model_Ebay_Template_Manager::TEMPLATE_RETURN;

        $templateManager = Mage::getModel('M2ePro/Ebay_Template_Manager');
        $templateManager->setTemplate($template);

        $listingProducts = $templateManager->getAffectedItems(
            Ess_M2ePro_Model_Ebay_Template_Manager::OWNER_LISTING_PRODUCT,
            $this->getId(),
            array(), $asObjects
        );

        foreach ($listingProducts as $key => $listingProduct) {
            unset($listingProducts[$key]);
            $listingProducts[$listingProduct['id']] = $listingProduct;
        }

        $listings = $templateManager->getAffectedItems(
            Ess_M2ePro_Model_Ebay_Template_Manager::OWNER_LISTING,
            $this->getId()
        );

        foreach ($listings as $listing) {
            $tempListingProducts = $listing->getChildObject()->getAffectedListingProducts($template,$asObjects);

            foreach ($tempListingProducts as $listingProduct) {
                $listingProducts[$listingProduct['id']] = $listingProduct;
            }
        }

        return $listingProducts;
    }

    // #######################################

    public function setIsNeedSynchronize($newData, $oldData)
    {
        if (!$this->getResource()->isDifferent($newData,$oldData)) {
            return;
        }

        $ids = array();
        foreach ($this->getAffectedListingProducts() as $listingProduct) {
            $ids[] = (int)$listingProduct['id'];
        }

        if (empty($ids)) {
            return;
        }

        $templates = array('returnTemplate');

        Mage::getSingleton('core/resource')->getConnection('core_read')->update(
            Mage::getSingleton('core/resource')->getTableName('M2ePro/Listing_Product'),
            array(
                'is_need_synchronize' => 1,
                'synch_reasons' => new Zend_Db_Expr(
                    "IF(synch_reasons IS NULL,
                        '".implode(',',$templates)."',
                        CONCAT(synch_reasons,'".','.implode(',',$templates)."')
                    )"
                )
            ),
            array('id IN ('.implode(',', $ids).')')
        );
    }

    // #######################################
}