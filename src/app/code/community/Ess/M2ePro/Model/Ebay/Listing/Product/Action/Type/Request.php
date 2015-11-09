<?php

/*
 * @copyright  Copyright (c) 2013 by  ESS-UA.
 */

abstract class Ess_M2ePro_Model_Ebay_Listing_Product_Action_Type_Request
    extends Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request
{
    /**
     * @var array
     */
    private $requestsTypes = array(
        'selling',
        'description',
        'categories',
        'variations',
        'shipping',
        'payment',
        'return'
    );

    /**
     * @var array[Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Abstract]
     */
    private $requests = array();

    // ########################################

    public function getData()
    {
        $this->initializeVariations();
        $this->beforeBuildDataEvent();

        $data = $this->getActionData();

        $data = $this->prepareFinalData($data);
        $this->collectRequestsWarningMessages();

        return $data;
    }

    // -----------------------------------------

    abstract protected function getActionData();

    // ########################################

    protected function initializeVariations()
    {
        /** @var Ess_M2ePro_Model_Ebay_Listing_Product_Variation_Updater $variationUpdater */
        $variationUpdater = Mage::getModel('M2ePro/Ebay_Listing_Product_Variation_Updater');
        $variationUpdater->process($this->getListingProduct());
        $variationUpdater->afterMassProcessEvent();

        $isVariationItem = $this->getEbayListingProduct()->isVariationsReady();

        $this->setIsVariationItem($isVariationItem);

        $validateVariationsKey = Ess_M2ePro_Model_Ebay_Listing_Product_Variation_Updater::VALIDATE_MESSAGE_DATA_KEY;

        if ($this->getListingProduct()->hasData($validateVariationsKey)) {

            $this->addWarningMessage(
                Mage::helper('M2ePro')->__(
                    $this->getListingProduct()->getData($validateVariationsKey)
                )
            );

            $this->getListingProduct()->unsetData($validateVariationsKey);
        }
    }

    protected function beforeBuildDataEvent() {}

    // -----------------------------------------

    protected function prepareFinalData(array $data)
    {
        $data['is_eps_ebay_images_mode'] = $this->getIsEpsImagesMode();

        if (!isset($data['out_of_stock_control'])) {
            $data['out_of_stock_control'] = $this->getOutOfStockControlMode();
        }

        $data = $this->replaceVariationSpecificsNames($data);
        $data = $this->resolveVariationAndItemSpecificsConflict($data);
        $data = $this->removeVariationsInstances($data);

        return $data;
    }

    protected function replaceVariationSpecificsNames(array $data)
    {
        if (!$this->getIsVariationItem() || !$this->getMagentoProduct()->isConfigurableType() ||
            empty($data['variations_sets']) || !is_array($data['variations_sets'])) {

            return $data;
        }

        $additionalData = $this->getListingProduct()->getAdditionalData();

        if (empty($additionalData['variations_specifics_replacements'])) {
            return $data;
        }

        $data = $this->doReplaceVariationSpecifics($data, $additionalData['variations_specifics_replacements']);
        return $data;
    }

    protected function resolveVariationAndItemSpecificsConflict(array $data)
    {
        if (!$this->getIsVariationItem() ||
            empty($data['item_specifics']) || !is_array($data['item_specifics']) ||
            empty($data['variations_sets']) || !is_array($data['variations_sets'])) {

            return $data;
        }

        $variationAttributes = array_keys($data['variations_sets']);
        $variationAttributes = array_map('strtolower', $variationAttributes);

        foreach ($data['item_specifics'] as $key => $itemSpecific) {

            if (!in_array(strtolower($itemSpecific['name']), $variationAttributes)) {
                continue;
            }

            unset($data['item_specifics'][$key]);

            $this->addWarningMessage(
                Mage::helper('M2ePro')->__(
                    'Item Specific "%specific_name%" will not be sent to eBay, because your Variational Product varies
                    by the Attribute with the same Label. In case M2E Pro will send this information twice, eBay will
                    return an error. So M2E Pro ignores the Value of this Item Specific to prevent the error message.',
                    $itemSpecific['name']
                )
            );
        }

        return $data;
    }

    protected function removeVariationsInstances(array $data)
    {
        if (isset($data['variation']) && is_array($data['variation'])) {
            foreach ($data['variation'] as &$variation) {
                unset($variation['_instance_']);
            }
        }

        return $data;
    }

    protected function doReplaceVariationSpecifics(array $data, array $replacements)
    {
        if (isset($data['variation_image']['specific'])) {

            foreach ($replacements as $findIt => $replaceBy) {

                if ($data['variation_image']['specific'] == $findIt) {
                    $data['variation_image']['specific'] = $replaceBy;
                }
            }
        }

        foreach ($data['variation'] as &$variationItem) {
            foreach ($replacements as $findIt => $replaceBy) {

                if (!isset($variationItem['specifics'][$findIt])) {
                   continue;
                }

                $variationItem['specifics'][$replaceBy] = $variationItem['specifics'][$findIt];
                unset($variationItem['specifics'][$findIt]);
            }
        }

        foreach ($replacements as $findIt => $replaceBy) {

            if (!isset($data['variations_sets'][$findIt])) {
                continue;
            }

            $data['variations_sets'][$replaceBy] = $data['variations_sets'][$findIt];
            unset($data['variations_sets'][$findIt]);

            // M2ePro_TRANSLATIONS
            // The Variational Attribute Label "%replaced_it%" was changed to "%replaced_by%". For Item Specific "%replaced_by%" you select an Attribute by which your Variational Item varies. As it is impossible to send a correct Value for this Item Specific, it’s Label will be used as Variational Attribute Label instead of "%replaced_it%". This replacement cannot be edit in future by Relist/Revise Actions.
            $this->addWarningMessage(
                Mage::helper('M2ePro')->__(
                    'The Variational Attribute Label "%replaced_it%" was changed to "%replaced_by%". For Item Specific
                    "%replaced_by%" you select an Attribute by which your Variational Item varies. As it is impossible
                    to send a correct Value for this Item Specific, it’s Label will be used as Variational Attribute
                    Label instead of "%replaced_it%". This replacement cannot be edit in future by
                    Relist/Revise Actions.',
                    $findIt, $replaceBy
                )
            );
        }

        return $data;
    }

    // -----------------------------------------

    protected function collectRequestsWarningMessages()
    {
        foreach ($this->requestsTypes as $requestType) {

            $messages = $this->getRequest($requestType)->getWarningMessages();

            foreach ($messages as $message) {
                $this->addWarningMessage($message);
            }
        }
    }

    // ----------------------------------------

    protected function getIsEpsImagesMode()
    {
        $additionalData = $this->getListingProduct()->getAdditionalData();

        if (!isset($additionalData['is_eps_ebay_images_mode'])) {
            return NULL;
        }

        return $additionalData['is_eps_ebay_images_mode'];
    }

    protected function getOutOfStockControlMode()
    {
        $additionalData = $this->getListingProduct()->getAdditionalData();

        if (!isset($additionalData['out_of_stock_control'])) {
            return NULL;
        }

        return $additionalData['out_of_stock_control'];
    }

    // ########################################

    /**
     * @return Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Selling
     */
    public function getRequestSelling()
    {
        return $this->getRequest('selling');
    }

    /**
     * @return Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Description
     */
    public function getRequestDescription()
    {
        return $this->getRequest('description');
    }

    // ----------------------------------------

    /**
     * @return Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Variations
     */
    public function getRequestVariations()
    {
        return $this->getRequest('variations');
    }

    /**
     * @return Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Categories
     */
    public function getRequestCategories()
    {
        return $this->getRequest('categories');
    }

    // ----------------------------------------

    /**
     * @return Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Payment
     */
    public function getRequestPayment()
    {
        return $this->getRequest('payment');
    }

    /**
     * @return Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Shipping
     */
    public function getRequestShipping()
    {
        return $this->getRequest('shipping');
    }

    /**
     * @return Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Return
     */
    public function getRequestReturn()
    {
        return $this->getRequest('return');
    }

    // ########################################

    /**
     * @param $type
     * @return Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Abstract
     */
    private function getRequest($type)
    {
        if (!isset($this->requests[$type])) {

            /** @var Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Abstract $request */
            $request = Mage::getModel('M2ePro/Ebay_Listing_Product_Action_Request_'.ucfirst($type));

            $request->setParams($this->getParams());
            $request->setListingProduct($this->getListingProduct());
            $request->setIsVariationItem($this->getIsVariationItem());
            $request->setConfigurator($this->getConfigurator());

            $this->requests[$type] = $request;
        }

        return $this->requests[$type];
    }

    // ########################################
}