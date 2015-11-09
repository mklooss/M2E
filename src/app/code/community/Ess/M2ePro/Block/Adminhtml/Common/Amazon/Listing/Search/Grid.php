<?php

/*
 * @copyright  Copyright (c) 2013 by  ESS-UA.
 */

class Ess_M2ePro_Block_Adminhtml_Common_Amazon_Listing_Search_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    // ####################################

    public function __construct()
    {
        parent::__construct();

        // Initialization block
        //------------------------------
        $this->setId('amazonListingSearchGrid');
        //------------------------------

        // Set default values
        //------------------------------
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        //------------------------------
    }

    // ####################################

    protected function _prepareCollection()
    {
        // Get collection products in listing
        //--------------------------------
        $listingProductCollection = Mage::helper('M2ePro/Component_Amazon')->getCollection('Listing_Product');
        $listingProductCollection->getSelect()->distinct();
        $listingProductCollection->getSelect()
                   ->join(array('l'=>Mage::getResourceModel('M2ePro/Listing')->getMainTable()),
                                '(`l`.`id` = `main_table`.`listing_id`)',
                                array('listing_title'=>'title','store_id','marketplace_id'))
                   ->join(array('al'=>Mage::getResourceModel('M2ePro/Amazon_Listing')->getMainTable()),
                                '(`al`.`listing_id` = `l`.`id`)',
                                array('template_selling_format_id'));
        //--------------------------------

        // only parents and individuals
        $listingProductCollection->getSelect()->where('second_table.variation_parent_id IS NULL');

        // Communicate with magento product table
        //--------------------------------
        $dbSelect = Mage::getResourceModel('core/config')->getReadConnection()
                                     ->select()
                                     ->from(Mage::getSingleton('core/resource')
                                                            ->getTableName('catalog_product_entity_varchar'),
                                                                           new Zend_Db_Expr('MAX(`store_id`)'))
                                     ->where("`entity_id` = `main_table`.`product_id`")
                                     ->where("`attribute_id` = `ea`.`attribute_id`")
                                     ->where("`store_id` = 0 OR `store_id` = `l`.`store_id`");

        $listingProductCollection->getSelect()
                //->join(array('csi'=>Mage::getSingleton('core/resource')->getTableName('cataloginventory_stock_item')),
//                             '(csi.product_id = `main_table`.product_id)',array('qty'))
                   ->join(array('cpe'=>Mage::getSingleton('core/resource')->getTableName('catalog_product_entity')),
                                '(cpe.entity_id = `main_table`.product_id)',
                                array('magento_sku'=>'sku'))
                   ->join(array('cisi'=>Mage::getSingleton('core/resource')
                                                ->getTableName('cataloginventory_stock_item')),
                                '(cisi.product_id = `main_table`.product_id AND cisi.stock_id = 1)',
                                array('is_in_stock'))
                   ->join(array('cpev'=>Mage::getSingleton('core/resource')
                                                ->getTableName('catalog_product_entity_varchar')),
                                "(`cpev`.`entity_id` = `main_table`.product_id)",
                                array('value'))
                   ->join(array('ea'=>Mage::getSingleton('core/resource')->getTableName('eav_attribute')),
                                '(`cpev`.`attribute_id` = `ea`.`attribute_id` AND `ea`.`attribute_code` = \'name\')',
                                array())
                   ->where('`cpev`.`store_id` = ('.$dbSelect->__toString().')');
        //--------------------------------

        $listingProductCollection->getSelect()->reset(Zend_Db_Select::COLUMNS);
        $listingProductCollection->getSelect()->columns(
            array(
                'is_m2epro_listing'             => new Zend_Db_Expr('1'),
                'magento_sku'                   => 'cpe.sku',
                'is_in_stock'                   => 'cisi.is_in_stock',
                'product_name'                  => 'cpev.value',
                'listing_title'                 => 'l.title',
                'store_id'                      => 'l.store_id',
                'account_id'                    => 'l.account_id',
                'marketplace_id'                => 'l.marketplace_id',
                'template_selling_format_id'    => 'al.template_selling_format_id',
                'listing_product_id'            => 'main_table.id',
                'product_id'                    => 'main_table.product_id',
                'listing_id'                    => 'main_table.listing_id',
                'status'                        => 'main_table.status',
                'is_general_id_owner'           => 'second_table.is_general_id_owner',
                'general_id'                    => 'second_table.general_id',
                'is_afn_channel'                => 'second_table.is_afn_channel',
                'is_variation_parent'           => 'second_table.is_variation_parent',
                'online_sku'                    => 'second_table.sku',
                'online_qty'                    => 'second_table.online_qty',
                'online_price'                  => 'second_table.online_price',
                'online_sale_price'             => 'second_table.online_sale_price',
                'online_sale_price_start_date'  => 'second_table.online_sale_price_start_date',
                'online_sale_price_end_date'    => 'second_table.online_sale_price_end_date',
                'min_online_price'                     => 'IF(
                    (`t`.`variation_min_price` IS NULL),
                    IF(
                      `second_table`.`online_sale_price_start_date` IS NOT NULL AND
                      `second_table`.`online_sale_price_end_date` IS NOT NULL AND
                      `second_table`.`online_sale_price_start_date` <= CURRENT_DATE() AND
                      `second_table`.`online_sale_price_end_date` >= CURRENT_DATE(),
                      `second_table`.`online_sale_price`,
                      `second_table`.`online_price`
                    ),
                    `t`.`variation_min_price`
                )',
                'max_online_price'                     => 'IF(
                    (`t`.`variation_max_price` IS NULL),
                    IF(
                      `second_table`.`online_sale_price_start_date` IS NOT NULL AND
                      `second_table`.`online_sale_price_end_date` IS NOT NULL AND
                      `second_table`.`online_sale_price_start_date` <= CURRENT_DATE() AND
                      `second_table`.`online_sale_price_end_date` >= CURRENT_DATE(),
                      `second_table`.`online_sale_price`,
                      `second_table`.`online_price`
                    ),
                    `t`.`variation_max_price`
                )'
            )
        );
        $listingProductCollection->getSelect()->joinLeft(
            new Zend_Db_Expr('(
                SELECT
                    `malp`.`variation_parent_id`,
                    MIN(
                        IF(
                            `malp`.`online_sale_price_start_date` IS NOT NULL AND
                            `malp`.`online_sale_price_end_date` IS NOT NULL AND
                            `malp`.`online_sale_price_start_date` <= CURRENT_DATE() AND
                            `malp`.`online_sale_price_end_date` >= CURRENT_DATE(),
                            `malp`.`online_sale_price`,
                            `malp`.`online_price`
                        )
                    ) as variation_min_price,
                    MAX(
                        IF(
                            `malp`.`online_sale_price_start_date` IS NOT NULL AND
                            `malp`.`online_sale_price_end_date` IS NOT NULL AND
                            `malp`.`online_sale_price_start_date` <= CURRENT_DATE() AND
                            `malp`.`online_sale_price_end_date` >= CURRENT_DATE(),
                            `malp`.`online_sale_price`,
                            `malp`.`online_price`
                        )
                    ) as variation_max_price
                FROM `'. Mage::getResourceModel('M2ePro/Amazon_Listing_Product')->getMainTable() .'` as malp
                INNER JOIN `'. Mage::getResourceModel('M2ePro/Listing_Product')->getMainTable() .'` AS `mlp`
                    ON (`malp`.`listing_product_id` = `mlp`.`id`)
                WHERE `mlp`.`status` IN (
                    ' . Ess_M2ePro_Model_Listing_Product::STATUS_LISTED . ',
                    ' . Ess_M2ePro_Model_Listing_Product::STATUS_STOPPED . '
                ) AND `malp`.`variation_parent_id` IS NOT NULL
                GROUP BY `malp`.`variation_parent_id`
            )'),
            'second_table.listing_product_id=t.variation_parent_id',
            array(
                'variation_min_price' => 'variation_min_price',
                'variation_max_price' => 'variation_max_price',
            )
        );

        //------------------------------
        $listingOtherCollection = Mage::helper('M2ePro/Component_Amazon')->getCollection('Listing_Other');
        $listingOtherCollection->getSelect()->distinct();

        // add stock availability, type id, status & visibility to select
        //------------------------------
        $listingOtherCollection->getSelect()
            ->joinLeft(
                array('cisi' => Mage::getResourceModel('cataloginventory/stock_item')->getMainTable()),
                '(`cisi`.`product_id` = `main_table`.`product_id` AND cisi.stock_id = 1)',
                array('is_in_stock'))
            ->joinLeft(array('cpe'=>Mage::getSingleton('core/resource')->getTableName('catalog_product_entity')),
                '(cpe.entity_id = `main_table`.product_id)',
                array('magento_sku'=>'sku'));
        //------------------------------

        $listingOtherCollection->getSelect()->reset(Zend_Db_Select::COLUMNS);
        $listingOtherCollection->getSelect()->columns(
            array(
                'is_m2epro_listing'             => new Zend_Db_Expr(0),
                'magento_sku'                   => 'cpe.sku',
                'is_in_stock'                   => 'cisi.is_in_stock',
                'product_name'                  => 'second_table.title',
                'listing_title'                 => new Zend_Db_Expr('NULL'),
                'store_id'                      => new Zend_Db_Expr(0),
                'account_id'                    => 'main_table.account_id',
                'marketplace_id'                => 'main_table.marketplace_id',
                'template_selling_format_id'    => new Zend_Db_Expr('NULL'),
                'listing_product_id'            => new Zend_Db_Expr('NULL'),
                'product_id'                    => 'main_table.product_id',
                'listing_id'                    => new Zend_Db_Expr('NULL'),
                'status'                        => 'main_table.status',
                'is_general_id_owner'           => new Zend_Db_Expr('NULL'),
                'general_id'                    => 'second_table.general_id',
                'is_afn_channel'                => 'second_table.is_afn_channel',
                'is_variation_parent'           => new Zend_Db_Expr('NULL'),
                'online_sku'                    => 'second_table.sku',
                'online_qty'                    => 'second_table.online_qty',
                'online_price'                  => 'second_table.online_price',
                'online_sale_price'             => new Zend_Db_Expr('NULL'),
                'online_sale_price_start_date'  => new Zend_Db_Expr('NULL'),
                'online_sale_price_end_date'    => new Zend_Db_Expr('NULL'),
                'min_online_price'              => 'second_table.online_price',
                'max_online_price'              => 'second_table.online_price',
                'variation_min_price'           => new Zend_Db_Expr('NULL'),
                'variation_max_price'           => new Zend_Db_Expr('NULL')
            )
        );
        //------------------------------

        //------------------------------
        $selects = array(
            $listingProductCollection->getSelect(),
            $listingOtherCollection->getSelect()
        );

        $unionSelect = Mage::getResourceModel('core/config')->getReadConnection()->select();
        $unionSelect->union($selects);

        $resultCollection = new Varien_Data_Collection_Db(Mage::getResourceModel('core/config')->getReadConnection());
        $resultCollection->getSelect()->reset()->from(
            array('main_table' => $unionSelect),
            array(
                'is_m2epro_listing',
                'magento_sku',
                'is_in_stock',
                'product_name',
                'listing_title',
                'store_id',
                'account_id',
                'marketplace_id',
                'template_selling_format_id',
                'listing_product_id',
                'product_id',
                'listing_id',
                'status',
                'is_general_id_owner',
                'general_id',
                'is_afn_channel',
                'is_variation_parent',
                'online_sku',
                'online_qty',
                'online_price',
                'online_sale_price',
                'online_sale_price_start_date',
                'online_sale_price_end_date',
                'min_online_price',
                'max_online_price',
                'variation_min_price',
                'variation_max_price'
            )
        );

//        exit($resultCollection->getSelect()->__toString());

        // Set collection to grid
        $this->setCollection($resultCollection);

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('product_id', array(
            'header'    => Mage::helper('M2ePro')->__('Product ID'),
            'align'     => 'right',
            'width'     => '100px',
            'type'      => 'number',
            'index'     => 'product_id',
            'filter_index' => 'product_id',
            'frame_callback' => array($this, 'callbackColumnProductId')
        ));

        $this->addColumn('name', array(
            'header'    => Mage::helper('M2ePro')->__('Product Title / Listing / Product SKU'),
            'align'     => 'left',
            //'width'     => '300px',
            'type'      => 'text',
            'index'     => 'product_name',
            'filter_index' => 'product_name',
            'frame_callback' => array($this, 'callbackColumnProductTitle'),
            'filter_condition_callback' => array($this, 'callbackFilterTitle')
        ));

        $this->addColumn('stock_availability',
            array(
                'header'=> Mage::helper('M2ePro')->__('Stock Availability'),
                'width' => '100px',
                'index' => 'is_in_stock',
                'filter_index' => 'is_in_stock',
                'type'  => 'options',
                'sortable'  => false,
                'options' => array(
                    1 => Mage::helper('M2ePro')->__('In Stock'),
                    0 => Mage::helper('M2ePro')->__('Out of Stock')
                ),
                'frame_callback' => array($this, 'callbackColumnStockAvailability')
        ));

        if (Mage::helper('M2ePro/View_Common')->is3rdPartyShouldBeShown(Ess_M2ePro_Helper_Component_Amazon::NICK)) {
            $this->addColumn('is_m2epro_listing', array(
                'header'    => Mage::helper('M2ePro')->__('Listing Type'),
                'align'     => 'left',
                'width'     => '100px',
                'type'      => 'options',
                'sortable'  => false,
                'index'     => 'is_m2epro_listing',
                'options'   => array(
                    1 => Mage::helper('M2ePro')->__('M2E Pro'),
                    0 => Mage::helper('M2ePro')->__('3rd Party')
                )
            ));
        }

        $this->addColumn('sku', array(
            'header' => Mage::helper('M2ePro')->__('SKU'),
            'align' => 'left',
            'width' => '150px',
            'type' => 'text',
            'index' => 'online_sku',
            'filter_index' => 'online_sku',
            'frame_callback' => array($this, 'callbackColumnAmazonSku')
        ));

        $this->addColumn('general_id', array(
            'header' => Mage::helper('M2ePro')->__('ASIN / ISBN'),
            'align' => 'left',
            'width' => '100px',
            'type' => 'text',
            'index' => 'general_id',
            'filter_index' => 'general_id',
            'frame_callback' => array($this, 'callbackColumnGeneralId')
        ));

        $this->addColumn('online_qty', array(
            'header' => Mage::helper('M2ePro')->__('QTY'),
            'align' => 'right',
            'width' => '70px',
            'type' => 'number',
            'index' => 'online_qty',
            'filter_index' => 'online_qty',
            'frame_callback' => array($this, 'callbackColumnAvailableQty')
        ));

        $dir = $this->getParam($this->getVarNameDir(), $this->_defaultDir);

        if ($dir == 'desc') {
            $priceSortField = 'max_online_price';
        } else {
            $priceSortField = 'min_online_price';
        }

        $this->addColumn('online_price', array(
            'header' => Mage::helper('M2ePro')->__('Price'),
            'align' => 'right',
            'width' => '70px',
            'type' => 'number',
            'index' => $priceSortField,
            'filter_index' => $priceSortField,
            'frame_callback' => array($this, 'callbackColumnPrice'),
            'filter_condition_callback' => array($this, 'callbackFilterPrice')
        ));

        $this->addColumn('is_afn_channel', array(
            'header' => Mage::helper('M2ePro')->__('Fulfillment'),
            'width' => '90px',
            'index' => 'is_afn_channel',
            'filter_index' => 'is_afn_channel',
            'type' => 'options',
            'sortable' => false,
            'options' => array(
                0 => Mage::helper('M2ePro')->__('Merchant'),
                1 => Mage::helper('M2ePro')->__('Amazon')
            ),
            'frame_callback' => array($this, 'callbackColumnAfnChannel')
        ));

        $this->addColumn('status', array(
            'header' => Mage::helper('M2ePro')->__('Status'),
            'width' => '125px',
            'index' => 'status',
            'filter_index' => 'status',
            'type' => 'options',
            'sortable' => false,
            'options' => array(
                Ess_M2ePro_Model_Listing_Product::STATUS_UNKNOWN => Mage::helper('M2ePro')->__('Unknown'),
                Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED => Mage::helper('M2ePro')->__('Not Listed'),
                Ess_M2ePro_Model_Listing_Product::STATUS_LISTED => Mage::helper('M2ePro')->__('Active'),
                Ess_M2ePro_Model_Listing_Product::STATUS_STOPPED => Mage::helper('M2ePro')->__('Inactive'),
                Ess_M2ePro_Model_Listing_Product::STATUS_BLOCKED => Mage::helper('M2ePro')->__('Inactive (Blocked)')
            ),
            'frame_callback' => array($this, 'callbackColumnStatus')
        ));

        $this->addColumn('goto_listing_item', array(
            'header'    => Mage::helper('M2ePro')->__('Manage'),
            'align'     => 'center',
            'width'     => '50px',
            'type'      => 'text',
            'filter'    => false,
            'sortable'  => false,
            'frame_callback' => array($this, 'callbackColumnActions')
        ));

        return parent::_prepareColumns();
    }

    // ####################################

    public function callbackColumnProductId($value, $row, $column, $isExport)
    {
        if (is_null($row->getData('product_id'))) {
            return Mage::helper('M2ePro')->__('N/A');
        }

        $productId = (int)$row->getData('product_id');
        $storeId = (int)$row->getData('store_id');

        $withoutImageHtml = '<a href="'
                            .$this->getUrl('adminhtml/catalog_product/edit',
                                           array('id' => $productId))
                            .'" target="_blank">'
                            .$productId
                            .'</a>';

        $showProductsThumbnails = (bool)(int)Mage::helper('M2ePro/Module')->getConfig()
                                                                          ->getGroupValue('/view/',
                                                                                          'show_products_thumbnails');
        if (!$showProductsThumbnails) {
            return $withoutImageHtml;
        }

        /** @var $magentoProduct Ess_M2ePro_Model_Magento_Product */
        $magentoProduct = Mage::getModel('M2ePro/Magento_Product');
        $magentoProduct->setProductId($productId);
        $magentoProduct->setStoreId($storeId);

        $imageUrlResized = $magentoProduct->getThumbnailImageLink();
        if (is_null($imageUrlResized)) {
            return $withoutImageHtml;
        }

        $imageHtml = $productId.'<hr/><img src="'.$imageUrlResized.'" />';
        $withImageHtml = str_replace('>'.$productId.'<','>'.$imageHtml.'<',$withoutImageHtml);

        return $withImageHtml;
    }

    public function callbackColumnProductTitle($value, $row, $column, $isExport)
    {
        $value = '<span>'.Mage::helper('M2ePro')->escapeHtml($value).'</span>';

        if (!is_null($row->getData('listing_id'))) {
            $urlParams = array();
            $urlParams['id'] = $row->getData('listing_id');
            $urlParams['back'] = Mage::helper('M2ePro')->makeBackUrlParam('*/adminhtml_common_amazon_listing/search');

            $listingUrl = $this->getUrl('*/adminhtml_common_amazon_listing/view',$urlParams);
            $listingTitle = Mage::helper('M2ePro')->escapeHtml($row->getData('listing_title'));

            if (strlen($listingTitle) > 50) {
                $listingTitle = substr($listingTitle, 0, 50) . '...';
            }

            $value .= '<br/><hr style="border:none; border-top:1px solid silver; margin: 2px 0px;"/>';
            $value .= '<strong>'
                      .Mage::helper('M2ePro')->__('Listing')
                      .': </strong> <a href="'
                      .$listingUrl
                      .'">'
                      .$listingTitle
                      .'</a>';
        }

        if (!is_null($row->getData('magento_sku'))) {
            $tempSku = $row->getData('magento_sku');

            $value .= '<br/><strong>'
                . Mage::helper('M2ePro')->__('SKU')
                . ':</strong> '
                . Mage::helper('M2ePro')->escapeHtml($tempSku);
        }

        if (is_null($row->getData('listing_product_id'))) {
            return $value;
        }

        $listingProductId = (int)$row->getData('listing_product_id');
        /** @var Ess_M2ePro_Model_Listing_Product $listingProduct */
        $listingProduct = Mage::helper('M2ePro/Component_Amazon')->getObject('Listing_Product',$listingProductId);

        if ($listingProduct->getChildObject()->getVariationManager()->isVariationParent()) {
            $productOptions = $listingProduct->getChildObject()->getVariationManager()
                ->getTypeModel()->getProductAttributes();

            $value .= '<div style="font-size: 11px; font-weight: bold; color: grey;"><br/>';
            $value .= implode(', ', $productOptions);
            $value .= '</div>';
        }

        if ($listingProduct->getChildObject()->getVariationManager()->isIndividualType() &&
            $listingProduct->getChildObject()->getVariationManager()->getTypeModel()->isVariationProductMatched()
        ) {
            $productOptions = $listingProduct->getChildObject()
                ->getVariationManager()->getTypeModel()->getProductOptions();

            $value .= '<br/>';
            $value .= '<div style="font-size: 11px; color: grey;"><br/>';
            foreach ($productOptions as $attribute => $option) {
                !$option && $option = '--';
                $value .= '<strong>' . Mage::helper('M2ePro')->escapeHtml($attribute) .
                    '</strong>:&nbsp;' . Mage::helper('M2ePro')->escapeHtml($option) . '<br/>';
            }
            $value .= '</div>';
            $value .= '<br/>';
        }

        return $value;
    }

    public function callbackColumnStockAvailability($value, $row, $column, $isExport)
    {
        if (is_null($row->getData('is_in_stock'))) {
            return Mage::helper('M2ePro')->__('N/A');
        }

        if ((int)$row->getData('is_in_stock') <= 0) {
            return '<span style="color: red;">'.$value.'</span>';
        }

        return $value;
    }

    public function callbackColumnAmazonSku($value, $row, $column, $isExport)
    {
        if ((!$row->getData('is_variation_parent') &&
            $row->getData('status') == Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED) ||
            ($row->getData('is_variation_parent') && $row->getData('general_id') == '')) {

            return '<span style="color: gray;">' . Mage::helper('M2ePro')->__('Not Listed') . '</span>';
        }

        if (is_null($value) || $value === '') {
            return Mage::helper('M2ePro')->__('N/A');
        }

        return $value;
    }

    public function callbackColumnGeneralId($value, $row, $column, $isExport)
    {
        if (empty($value)) {

            if ((int)$row->getData('status') != Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED) {
                return '<i style="color:gray;">'.Mage::helper('M2ePro')->__('receiving...').'</i>';
            }

            if ($row->getData('is_general_id_owner')) {
                return Mage::helper('M2ePro')->__('New ASIN/ISBN');
            }

            return Mage::helper('M2ePro')->__('N/A');
        }

        $url = Mage::helper('M2ePro/Component_Amazon')->getItemUrl($value, $row->getData('marketplace_id'));
        return '<a href="'.$url.'" target="_blank">'.$value.'</a>';
    }

    public function callbackColumnAvailableQty($value, $row, $column, $isExport)
    {
        if ((!$row->getData('is_variation_parent') &&
            $row->getData('status') == Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED) ||
            ($row->getData('is_variation_parent') && $row->getData('general_id') == '')) {

            return '<span style="color: gray;">' . Mage::helper('M2ePro')->__('Not Listed') . '</span>';
        }

        if ($row->getData('is_afn_channel')) {
            return Mage::helper('M2ePro')->__('N/A');
        }

        if (is_null($value) || $value === '') {
            if ($row->getData('status') != Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED) {
                return '<i style="color:gray;">receiving...</i>';
            }

            return Mage::helper('M2ePro')->__('N/A');
        }

        if ($value <= 0) {
            return '<span style="color: red;">0</span>';
        }

        return $value;
    }

    public function callbackColumnPrice($value, $row, $column, $isExport)
    {
        if ((!$row->getData('is_variation_parent') &&
            $row->getData('status') == Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED) ||
            ($row->getData('is_variation_parent') && $row->getData('general_id') == '')) {

            return '<span style="color: gray;">' . Mage::helper('M2ePro')->__('Not Listed') . '</span>';
        }

        $onlineMinPrice = $row->getData('min_online_price');
        $onlineMaxPrice = $row->getData('max_online_price');

        if (is_null($onlineMinPrice) || $onlineMinPrice === '') {
            if ($row->getData('status') == Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED ||
                $row->getData('is_variation_parent')
            ) {
                return Mage::helper('M2ePro')->__('N/A');
            } else {
                return '<i style="color:gray;">receiving...</i>';
            }
        }

        $marketplaceId = $row->getData('marketplace_id');
        $currency = Mage::helper('M2ePro/Component_Amazon')
            ->getCachedObject('Marketplace',$marketplaceId)
            ->getChildObject()
            ->getDefaultCurrency();

        if ($row->getData('is_variation_parent')) {
            $onlineMinPriceStr = Mage::app()->getLocale()->currency($currency)->toCurrency($onlineMinPrice);
            $onlineMaxPriceStr = Mage::app()->getLocale()->currency($currency)->toCurrency($onlineMaxPrice);

            return $onlineMinPriceStr . (($onlineMinPrice != $onlineMaxPrice) ? ' - ' . $onlineMaxPriceStr :  '');
        }

        $onlinePrice = $row->getData('online_price');
        if ((float)$onlinePrice <= 0) {
            $priceValue = '<span style="color: #f00;">0</span>';
        } else {
            $priceValue = Mage::app()->getLocale()->currency($currency)->toCurrency($onlinePrice);
        }

        $resultHtml = '';

        $salePrice = $row->getData('online_sale_price');
        if (!$row->getData('is_variation_parent') && (float)$salePrice > 0) {
            $currentTimestamp = strtotime(Mage::helper('M2ePro')->getCurrentGmtDate(false,'Y-m-d 00:00:00'));

            $startDateTimestamp = strtotime($row->getData('online_sale_price_start_date'));
            $endDateTimestamp   = strtotime($row->getData('online_sale_price_end_date'));

            if ($currentTimestamp <= $endDateTimestamp) {
                $iconHelpPath = $this->getSkinUrl('M2ePro/images/i_logo.png');
                $toolTipIconPath = $this->getSkinUrl('M2ePro/images/i_icon.png');

                $dateFormat = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM);

                $fromDate = Mage::app()->getLocale()->date(
                    $row->getData('online_sale_price_start_date'), $dateFormat
                )->toString($dateFormat);
                $toDate = Mage::app()->getLocale()->date(
                    $row->getData('online_sale_price_end_date'), $dateFormat
                )->toString($dateFormat);

                $intervalHtml = '<img class="tool-tip-image"
                                 style="vertical-align: middle;"
                                 src="'.$toolTipIconPath.'"><span class="tool-tip-message" style="display:none;
                                                                  text-align: left;
                                                                  width: 110px;
                                                                  background: #E3E3E3;">
                                <img src="'.$iconHelpPath.'">
                                <span style="color:gray;">
                                    <strong>From:</strong> '.$fromDate.'<br/>
                                    <strong>To:</strong> '.$toDate.'
                                </span>
                            </span>';

                $salePriceValue = Mage::app()->getLocale()->currency($currency)->toCurrency($salePrice);

                if ($currentTimestamp >= $startDateTimestamp &&
                    $currentTimestamp <= $endDateTimestamp &&
                    $salePrice < (float)$onlinePrice
                ) {
                    $resultHtml .= '<span style="color: grey; text-decoration: line-through;">'.$priceValue.'</span>';
                    $resultHtml .= '<br/>'.$intervalHtml.'&nbsp;'.$salePriceValue;
                } else {
                    $resultHtml .= $priceValue;
                    $resultHtml .= '<br/>'.$intervalHtml.
                        '<span style="color:gray;">'.'&nbsp;'.$salePriceValue.'</span>';
                }
            }
        }

        if (empty($resultHtml)) {
            $resultHtml = $priceValue;
        }

        return $resultHtml;
    }

    public function callbackColumnAfnChannel($value, $row, $column, $isExport)
    {
        if ((!$row->getData('is_variation_parent') &&
            $row->getData('status') == Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED) ||
            ($row->getData('is_variation_parent') && $row->getData('general_id') == '')) {

            return '<span style="color: gray;">' . Mage::helper('M2ePro')->__('Not Listed') . '</span>';
        }

        if (is_null($value) || $value === '') {
            return Mage::helper('M2ePro')->__('N/A');
        }

        return $value;
    }

    public function callbackColumnStatus($value, $row, $column, $isExport)
    {
        switch ($row->getData('status')) {

            case Ess_M2ePro_Model_Listing_Product::STATUS_UNKNOWN:
            case Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED:
                $value = '<span style="color: gray;">' . $value . '</span>';
                break;

            case Ess_M2ePro_Model_Listing_Product::STATUS_LISTED:
                $value = '<span style="color: green;">' . $value . '</span>';
                break;

            case Ess_M2ePro_Model_Listing_Product::STATUS_STOPPED:
                $value = '<span style="color: red;">'.$value.'</span>';
                break;

            case Ess_M2ePro_Model_Listing_Product::STATUS_BLOCKED:
                $value = '<span style="color: orange; font-weight: bold;">'.$value.'</span>';
                break;

            default:
                break;
        }

        if (is_null($row->getData('listing_product_id'))) {
            return $value;
        }

        $listingProductId = (int)$row->getData('listing_product_id');
        /** @var Ess_M2ePro_Model_Listing_Product $listingProduct */
        $listingProduct = Mage::helper('M2ePro/Component_Amazon')->getObject('Listing_Product',$listingProductId);

        $tempLocks = $listingProduct->getObjectLocks();

        foreach ($tempLocks as $lock) {

            switch ($lock->getTag()) {

                case 'list_action':
                    $value .= '<br/><span style="color: #605fff">[List in Progress...]</span>';
                    break;

                case 'relist_action':
                    $value .= '<br/><span style="color: #605fff">[Relist in Progress...]</span>';
                    break;

                case 'revise_action':
                    $value .= '<br/><span style="color: #605fff">[Revise in Progress...]</span>';
                    break;

                case 'stop_action':
                    $value .= '<br/><span style="color: #605fff">[Stop in Progress...]</span>';
                    break;

                case 'stop_and_remove_action':
                    $value .= '<br/><span style="color: #605fff">[Stop And Remove in Progress...]</span>';
                    break;

                case 'delete_and_remove_action':
                    $value .= '<br/><span style="color: #605fff">[Remove in Progress...]</span>';
                    break;

                default:
                    break;

            }
        }

        return $value;
    }

    public function callbackColumnActions($value, $row, $column, $isExport)
    {
        $altTitle = Mage::helper('M2ePro')->escapeHtml(Mage::helper('M2ePro')->__('Go to Listing'));
        $iconSrc = $this->getSkinUrl('M2ePro/images/goto_listing.png');

        if ($row->getData('is_m2epro_listing')) {
            $url = $this->getUrl('*/adminhtml_common_amazon_listing/view/',array(
                'id' => $row->getData('listing_id'),
                'filter' => base64_encode(
                    'product_id[from]='.(int)$row->getData('product_id')
                    .'&product_id[to]='.(int)$row->getData('product_id')
                )
            ));
        } else {
            $url = $this->getUrl('*/adminhtml_common_amazon_listing_other/view/', array(
                'account' => $row->getData('account_id'),
                'marketplace' => $row->getData('marketplace_id'),
                'filter' => base64_encode(
                    'title='.$row->getData('online_sku')
                )
            ));
        }

        $html = <<<HTML
<div style="float:right; margin:5px 15px 0 0;">
    <a title="{$altTitle}" target="_blank" href="{$url}"><img src="{$iconSrc}" alt="{$altTitle}" /></a>
</div>
HTML;

        return $html;
    }

    // ####################################

    protected function callbackFilterTitle($collection, $column)
    {
        $value = $column->getFilter()->getValue();

        if ($value == null) {
            return;
        }

        $collection->getSelect()
            ->where('product_name LIKE ? OR magento_sku LIKE ? OR listing_title LIKE ?', '%'.$value.'%');
    }

    protected function callbackFilterPrice($collection, $column)
    {
        $value = $column->getFilter()->getValue();

        if (empty($value)) {
            return;
        }

        $condition = '';

        if (isset($value['from']) && $value['from'] != '') {
            $condition = 'min_online_price >= \''.$value['from'].'\'';
        }
        if (isset($value['to']) && $value['to'] != '') {
            if (isset($value['from']) && $value['from'] != '') {
                $condition .= ' AND ';
            }
            $condition .= 'min_online_price <= \''.$value['to'].'\'';
        }

        $condition = '(' . $condition . ') OR (';

        if (isset($value['from']) && $value['from'] != '') {
            $condition .= 'max_online_price >= \''.$value['from'].'\'';
        }
        if (isset($value['to']) && $value['to'] != '') {
            if (isset($value['from']) && $value['from'] != '') {
                $condition .= ' AND ';
            }
            $condition .= 'max_online_price <= \''.$value['to'].'\'';
        }

        $condition .= ')';

        $collection->getSelect()->where($condition);
    }

    // ####################################

    public function getGridUrl()
    {
        return $this->getUrl('*/adminhtml_common_amazon_listing/searchGrid', array('_current'=>true));
    }

    public function getRowUrl($row)
    {
        return false;
    }

    // ####################################
}