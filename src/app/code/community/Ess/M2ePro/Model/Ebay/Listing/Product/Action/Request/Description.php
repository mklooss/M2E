<?php

/*
 * @copyright  Copyright (c) 2013 by  ESS-UA.
 */

class Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Description
    extends Ess_M2ePro_Model_Ebay_Listing_Product_Action_Request_Abstract
{
    const PRODUCT_DETAILS_DOES_NOT_APPLY = 'Does Not Apply';
    const PRODUCT_DETAILS_UNBRANDED = 'Unbranded';

    /**
     * @var Ess_M2ePro_Model_Template_Description
     */
    private $descriptionTemplate = NULL;

    // ########################################

    public function getData()
    {
        $data = array();

        if ($this->getConfigurator()->isGeneralAllowed()) {

            $data = array_merge(
                array(
                    'hit_counter'          => $this->getEbayDescriptionTemplate()->getHitCounterType(),
                    'listing_enhancements' => $this->getEbayDescriptionTemplate()->getEnhancements(),
                    'item_condition_note'  => $this->getConditionNoteData(),
                    'product_details'      => $this->getProductDetailsData()
                ),
                $this->getConditionData()
            );
        }

        return array_merge(
            $data,
            $this->getTitleData(),
            $this->getSubtitleData(),
            $this->getDescriptionData(),
            $this->getImagesData()
        );
    }

    // ########################################

    public function getTitleData()
    {
        if (!$this->getConfigurator()->isTitleAllowed()) {
            return array();
        }

        $this->searchNotFoundAttributes();
        $data = $this->getDescriptionSource()->getTitle();
        $this->processNotFoundAttributes('Title');

        return array(
            'title' => $data
        );
    }

    public function getSubtitleData()
    {
        if (!$this->getConfigurator()->isSubtitleAllowed()) {
            return array();
        }

        $this->searchNotFoundAttributes();
        $data = $this->getDescriptionSource()->getSubTitle();
        $this->processNotFoundAttributes('Subtitle');

        return array(
            'subtitle' => $data
        );
    }

    public function getDescriptionData()
    {
        if (!$this->getConfigurator()->isDescriptionAllowed()) {
            return array();
        }

        $this->searchNotFoundAttributes();

        $data = $this->getDescriptionSource()->getDescription();
        $data = $this->getEbayListingProduct()->getDescriptionRenderer()->parseTemplate($data);

        $this->processNotFoundAttributes('Description');

        return array(
            'description' => $data
        );
    }

    // ----------------------------------------

    public function getImagesData()
    {
        if (!$this->getConfigurator()->isImagesAllowed()) {
            return array();
        }

        $this->searchNotFoundAttributes();

        $data = array(
            'gallery_type' => $this->getEbayDescriptionTemplate()->getGalleryType(),
            'images'       => $this->getDescriptionSource()->getGalleryImages(),
            'supersize'    => $this->getEbayDescriptionTemplate()->isUseSupersizeImagesEnabled()
        );

        $this->processNotFoundAttributes('Main Image / Gallery Images');

        return array(
            'images' => $data
        );
    }

    // ########################################

    public function getProductDetailsData()
    {
        $data = array();

        foreach (array('isbn','epid','upc','ean','brand','mpn') as $tempType) {

            if ($this->getIsVariationItem() && $tempType != 'brand') {
                continue;
            }

            if ($this->getEbayDescriptionTemplate()->isProductDetailsModeDoesNotApply($tempType)) {
                $data[$tempType] = ($tempType == 'brand') ? self::PRODUCT_DETAILS_UNBRANDED :
                                                            self::PRODUCT_DETAILS_DOES_NOT_APPLY;
                continue;
            }

            $this->searchNotFoundAttributes();
            $tempValue = $this->getDescriptionSource()->getProductDetail($tempType);

            if (!$this->processNotFoundAttributes(strtoupper($tempType))) {
                continue;
            }

            if (!$tempValue) {
                continue;
            }

            $data[$tempType] = $tempValue;
        }

        $data = $this->deleteNotAllowedIdentifier($data);

        if (empty($data)) {
            return $data;
        }

        $data['include_description'] = $this->getEbayDescriptionTemplate()->isProductDetailsIncludeDescription();
        $data['include_image'] = $this->getEbayDescriptionTemplate()->isProductDetailsIncludeImage();

        return $data;
    }

    // ----------------------------------------

    public function getConditionData()
    {
        $this->searchNotFoundAttributes();
        $data = $this->getDescriptionSource()->getCondition();

        if (!$this->processNotFoundAttributes('Condition')) {
            return array();
        }

        return array(
            'item_condition' => $data
        );
    }

    public function getConditionNoteData()
    {
        $this->searchNotFoundAttributes();
        $data = $this->getDescriptionSource()->getConditionNote();
        $this->processNotFoundAttributes('Seller Notes');

        return $data;
    }

    // ########################################

    /**
     * @return Ess_M2ePro_Model_Template_Description
     */
    private function getDescriptionTemplate()
    {
        if (is_null($this->descriptionTemplate)) {
            $this->descriptionTemplate = $this->getListingProduct()
                                              ->getChildObject()
                                              ->getDescriptionTemplate();
        }
        return $this->descriptionTemplate;
    }

    /**
     * @return Ess_M2ePro_Model_Ebay_Template_Description
     */
    private function getEbayDescriptionTemplate()
    {
        return $this->getDescriptionTemplate()->getChildObject();
    }

    /**
     * @return Ess_M2ePro_Model_Ebay_Template_Description_Source
     */
    private function getDescriptionSource()
    {
        return $this->getEbayListingProduct()->getDescriptionTemplateSource();
    }

    // ########################################

    private function deleteNotAllowedIdentifier(array $data)
    {
        if (empty($data)) {
            return $data;
        }

        $categoryId = $this->getEbayListingProduct()->getCategoryTemplateSource()->getMainCategory();
        $marketplaceId = $this->getMarketplace()->getId();
        $categoryFeatures = Mage::helper('M2ePro/Component_Ebay_Category_Ebay')
                                   ->getFeatures($categoryId, $marketplaceId);

        if (empty($categoryFeatures)) {
            return $data;
        }

        $statusDisabled = Ess_M2ePro_Helper_Component_Ebay_Category_Ebay::PRODUCT_IDENTIFIER_STATUS_DISABLED;

        foreach (array('ean','upc','isbn') as $identifier) {

            $key = $identifier.'_enabled';
            if (!isset($categoryFeatures[$key]) || $categoryFeatures[$key] != $statusDisabled) {
                continue;
            }

            if (isset($data[$identifier])) {

                unset($data[$identifier]);

                // M2ePro_TRANSLATIONS
                // The value of %type% was no sent because it is not allowed in this Category
                $this->addWarningMessage(
                    Mage::helper('M2ePro')->__(
                        'The value of %type% was no sent because it is not allowed in this Category',
                        Mage::helper('M2ePro')->__(strtoupper($identifier))
                    )
                );
            }
        }

        return $data;
    }

    // ########################################
}