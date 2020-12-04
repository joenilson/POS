<?php
/**
 * This file is part of POS plugin for FacturaScripts
 * Copyright (C) 2020 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
namespace FacturaScripts\Plugins\POS\Controller;

use FacturaScripts\Core\Lib\ExtendedController;

/**
 * Controller to list the items in the SesionPOS model
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class ListOperacionPOS extends ExtendedController\ListController
{

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'till-session-operations';
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
        $this->addView('ListOperacionPOS', 'OperacionPOS', 'till-session-operations', 'fas fa-money-bill-alt');
        $this->addSearchFields('ListOperacionPOS', ['nombrecliente']);

        $this->setSettings('ListOperacionPOS', 'btnNew', false);
    }
}
