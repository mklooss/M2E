<?php

/*
 * @copyright  Copyright (c) 2015 by  ESS-UA.
 */

class Ess_M2ePro_Model_Wizard_EbayProductDetails extends Ess_M2ePro_Model_Wizard
{
    protected $steps = array(
        'marketplacesSynchronization'
    );

    // ########################################

    public function getNick()
    {
        return 'ebayProductDetails';
    }

    // ########################################
}