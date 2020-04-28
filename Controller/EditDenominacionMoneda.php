<?php
/**
 * This file is part of EasyPOS plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
namespace FacturaScripts\Plugins\EasyPOS\Controller;

use FacturaScripts\Core\Lib\ExtendedController;

/**
 * Controller to edit a single item from the DenominacionMoneda model
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class EditDenominacionMoneda extends ExtendedController\EditController
{

    /**
     * Returns the model name
     */
    public function getModelClassName()
    {
        return 'DenominacionMoneda';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'currency-denomination';
        $pagedata['menu'] = 'admin';
        $pagedata['icon'] = 'fas fa-money-bill-alt';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }
}
