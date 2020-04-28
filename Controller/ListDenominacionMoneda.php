<?php
/**
 * This file is part of EasyPOS plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
namespace FacturaScripts\Plugins\EasyPOS\Controller;

use FacturaScripts\Core\Lib\ExtendedController;

/**
 * Controller to list the items in the DenominacionMoneda model
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class ListDenominacionMoneda extends ExtendedController\ListController
{

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'currency-denomination';
        $pagedata['icon'] = 'fas fa-money-bill-alt';
        $pagedata['menu'] = 'point-of-sale';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->addView('ListDenominacionMoneda', 'DenominacionMoneda', 'currency-denominations', 'fas fa-money-bill-alt');
        $this->addSearchFields('ListDenominacionMoneda', ['clave', 'coddivisa']);

        $this->addOrderBy('ListDenominacionMoneda', ['clave'], 'Clave');
        $this->addOrderBy('ListDenominacionMoneda', ['coddivisa'], 'code');
        $this->addOrderBy('ListDenominacionMoneda', ['valor'], 'value');
    }
}
