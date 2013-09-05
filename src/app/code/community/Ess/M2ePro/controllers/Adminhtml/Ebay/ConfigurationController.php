<?php

/*
 * @copyright  Copyright (c) 2013 by  ESS-UA.
 */

class Ess_M2ePro_Adminhtml_Ebay_ConfigurationController extends Ess_M2ePro_Controller_Adminhtml_Ebay_MainController
{
    //#############################################

    protected function _initAction()
    {
        $this->loadLayout()
            ->_title(Mage::helper('M2ePro')->__('Configuration'));

        return $this;
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('m2epro_ebay/configuration');
    }

    //#############################################

    public function indexAction()
    {
        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock(
                'M2ePro/adminhtml_ebay_configuration', '',
                array('active_tab' => Ess_M2ePro_Block_Adminhtml_Ebay_Configuration_Tabs::TAB_ID_GENERAL)
                )
            )->renderLayout();
    }

    public function saveAction()
    {
        Mage::helper('M2ePro/Module')->getConfig()->setGroupValue(
            '/view/ebay/', 'mode',
            $this->getRequest()->getParam('view_ebay_mode')
        );
        Mage::helper('M2ePro/Module')->getConfig()->setGroupValue(
            '/view/ebay/feedbacks/notification/', 'mode',
            (int)$this->getRequest()->getParam('view_ebay_feedbacks_notification_mode')
        );
        Mage::helper('M2ePro/Module')->getConfig()->setGroupValue(
            '/view/ebay/cron/notification/', 'mode',
            (int)$this->getRequest()->getParam('cron_notification_mode')
        );

        $this->_getSession()->addSuccess(Mage::helper('M2ePro')->__('Settings was successfully saved.'));
        $this->_redirectUrl($this->_getRefererUrl());
    }

    //#############################################
}