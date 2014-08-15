<?php

/*
 * @copyright  Copyright (c) 2013 by  ESS-UA.
 */

class Ess_M2ePro_Block_Adminhtml_Development_Info_Mysql_Module extends Mage_Adminhtml_Block_Widget
{
    // ########################################

    public function __construct()
    {
        parent::__construct();

        // Initialization block
        //------------------------------
        $this->setId('developmentDatabaseModule');
        //------------------------------

        $this->setTemplate('M2ePro/development/info/mysql/module.phtml');
    }

    // ########################################

    public function getInfoTables()
    {
        $tablesData = array_merge(
            $this->getConfigTables(),
            $this->getLocksAndChangeTables(),
            $this->getAdditionalTables()
        );

        /** @var $connRead Varien_Db_Adapter_Pdo_Mysql */
        $connRead = Mage::getSingleton('core/resource')->getConnection('core_read');
        $existTables = Mage::helper('M2ePro/Magento')->getMySqlTables();

        $tablesInfo = array();
        foreach ($tablesData as $category => $tables) {
            foreach ($tables as $table) {

                if(!in_array($table, $existTables)) {
                    $tablesInfo[$category][$table] = array(
                        'count' => 0, 'url'   => '#'
                    );
                    continue;
                }

                $moduleTable = Mage::getSingleton('core/resource')->getTableName($table);
                $dbSelect = $connRead->select()->from($moduleTable, new Zend_Db_Expr('COUNT(*)'));

                $tablesInfo[$category][$table]['count'] = $connRead->fetchOne($dbSelect);
                $tablesInfo[$category][$table]['url'] = $this->getUrl(
                    '*/adminhtml_development_database/manageTable', array('table' => $table)
                );
            }
        }

        return $tablesInfo;
    }

    // ########################################

    private function getConfigTables()
    {
        return array(
            'Config' => array(
                'm2epro_primary_config',
                'm2epro_config',
                'm2epro_synchronization_config'
            )
        );
    }

    private function getLocksAndChangeTables()
    {
        return array(
            'Locks / Changes' => array(
                'm2epro_lock_item',
                'm2epro_locked_object',
                'm2epro_product_change',
                'm2epro_order_change'
            )
        );
    }

    private function getAdditionalTables()
    {
        return array(
            'Additional' => array(
                'm2epro_processing_request',
                'm2epro_operation_history'
            )
        );
    }

    // ########################################
}