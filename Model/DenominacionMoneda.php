<?php
/**
 * This file is part of EasyPOS plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
namespace FacturaScripts\Plugins\EasyPOS\Model;

use FacturaScripts\Core\Model\Base;

/**
 * Cash denomination .
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class DenominacionMoneda extends Base\ModelClass
{
    use Base\ModelTrait;

    public $clave;
    public $coddivisa;
    public $valor;

    public static function primaryColumn()
    {
        return 'clave';
    }

    public static function tableName()
    {
        return 'denominacionesmoneda';
    }
}
