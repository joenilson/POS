<?php
/**
 * This file is part of POS plugin for FacturaScripts
 * Copyright (C) 2022 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */

namespace FacturaScripts\Plugins\POS\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\DenominacionMoneda;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\TerminalPuntoVenta;
use FacturaScripts\Plugins\POS\Model\TipoDocumentoPuntoVenta;

trait PointOfSaleTrait
{
    /**
     * @var PointOfSaleSession
     */
    protected $session;

    /**
     * @return array
     */
    public function getParentFamilies(): array
    {
        $where = [new DataBaseWhere('madre', NULL, 'IS')];
        return (new Familia())->all($where);
    }

    /**
     * Returns the cash payment method ID.
     *
     * @return string
     */
    public function getCashPaymentMethod(): string
    {
        foreach ($this->getTerminal()->getPaymenthMethods() as $element) if ($element->recibecambio) {
            return $element->codpago;
        }
        return '';
    }

    /**
     * @return array
     */
    public function getCustomButtons(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getCustomFields(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getCustomModals(): array
    {
        return [];
    }

    /**
     * @return Cliente
     */
    public function getDefaultCustomer(): Cliente
    {
        $customer = new Cliente();
        $customer->loadFromCode($this->getTerminal()->codcliente);

        return $customer;
    }

    /**
     * @return TipoDocumentoPuntoVenta
     */
    public function getDefaultDocument(): TipoDocumentoPuntoVenta
    {
        foreach ($this->getTerminal()->getDocumentTypes() as $element) if ($element->preferido) {
            return $element;
        }
        return new TipoDocumentoPuntoVenta();
    }

    /**
     * Returns all available denominations.
     *
     * @return array
     */
    public function getDenominations(): array
    {
        return (new DenominacionMoneda())->all([], ['valor' => 'ASC']);
    }

    /**
     * Returns fields available by user permissions.
     *
     * @return array
     */
    public function getFieldOptions(): array
    {
        return PointOfSaleForms::getFormsGrid($this->user->nick);
    }

    /**
     * Return some products for initial view
     *
     * @return array
     */
    public function getHomeProducts(): array
    {
        $product = new PointOfSaleProduct();

        return $product->search('', []);
    }

    /**
     * Returns a random token to use as transaction id.
     *
     * @return string
     */
    public function getNewToken(): string
    {
        return $this->multiRequestProtection->newToken();
    }

    /**
     * Return some products for initial view
     *
     * @param string $id
     * @param string $code
     * @return array
     */
    public function getProductImage(string $id, string $code): array
    {
        $product = new PointOfSaleProduct();

        return $product->getImages($id, $code);
    }

    /**
     * Return product images url list
     *
     * @param string $id
     * @param string $code
     * @return array
     */
    public function getProductImageList(string $id, string $code): array
    {
        $product = new PointOfSaleProduct();

        return $product->getImagesURL($id, $code);
    }

    /**
     * Returns all available payment methods.
     *
     * @return FormaPago[]
     */
    public function getPaymentMethods(): array
    {
        return $this->getTerminal()->getPaymenthMethods();
    }

    /**
     * @return string
     */
    public function getDefaultWarehouse(): string
    {
        return $this->getTerminal()->codalmacen ?: '';
    }

    /**
     * Get current user session.
     *
     * @return PointOfSaleSession
     */
    public function getSession(): PointOfSaleSession
    {
        return $this->session;
    }

    /**
     * Get current user session terminal.
     *
     * @return TerminalPuntoVenta
     */
    public function getTerminal(): TerminalPuntoVenta
    {
        return $this->session->getTerminal();
    }

    /**
     * Get current user session terminal.
     *
     * @return TerminalPuntoVenta[]
     */
    public function getTerminalFromCompany(): array
    {
        return $this->session->getTerminal()->allAvailable($this->user->idempresa);
    }

    /**
     * Read the log.
     *
     * @return array
     */
    protected function getMessages(): array
    {
        $messages = [];
        $level = ['critical', 'warning', 'notice', 'info', 'error'];

        foreach (self::toolBox()::log()::read('master', $level) as $m) {
            if (in_array($m['level'], array('warning', 'critical', 'error'))) {
                $messages[] = ['type' => 'warning', 'message' => $m['message']];
                continue;
            }

            $messages[] = ['type' => $m['level'], 'message' => $m['message']];
        }

        return $messages;
    }

    /**
     * Return POS setting value by given key.
     *
     * @param string $key
     * @return mixed
     */
    protected function getSetting(string $key)
    {
        return self::toolBox()::appSettings()::get('pointofsale', $key);
    }

        /**
     * @param $document
     * @return void;
     */
    protected function printVoucher($document, $payments)
    {
        $message = PointOfSalePrinter::salesTicket($document, $payments);

        $this->toolBox()->log()->info($message);
    }

    /**
     * Print closing voucher.
     *
     * @return void;
     */
    protected function printClosingVoucher()
    {
        $message = PointOfSalePrinter::cashupTicket(
            $this->session->getSession(), $this->empresa,
            $this->getTerminal()->anchopapel);

        $this->toolBox()->log()->info($message);
    }

    /**
     *
     */
    public function setFamilyFilter(): void
    {
        $codfamilia = $this->request->request->get('code', '');

        $where = [new DataBaseWhere('madre', $codfamilia)];

        $familia = new Familia();
        $familia->loadFromCode($codfamilia);

        $result = [
            'madre' => $familia->codfamilia ? $familia : '',
            'children' => $codfamilia ? $familia->all($where) : $this->getParentFamilies()
        ];

        $this->setResponse($result);
    }

    protected function setNewToken(): void
    {
        $this->token = $this->getNewToken();
    }

    /**
     * @param $content
     * @param bool $encode
     */
    protected function setResponse($content, bool $encode = true): void
    {
        $response = $encode ? json_encode($content) : $content;
        $this->response->setContent($response);
    }

    /**
     * @return bool
     */
    protected function validateRequest(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            $this->toolBox()->i18nLog()->warning('not-allowed-modify');
            $this->buildResponse();
            return false;
        }

        $this->token = $this->request->request->get('token');

        if (empty($this->token) || false === $this->multiRequestProtection->validate($this->token)) {
            $this->toolBox()->i18nLog()->warning('invalid-request');
            $this->toolBox()->i18nLog()->warning('invalid-token' . $this->token);
            $this->buildResponse();
            return false;
        }

        if ($this->multiRequestProtection->tokenExist($this->token)) {
            $this->toolBox()->i18nLog()->warning('duplicated-request');
            $this->buildResponse();
            return false;
        }

        $this->setNewToken();
        return true;
    }

    public function validateSettings(): bool
    {
        $result = true;

        $paymentMethod = $this->getPaymentMethods();
        if (empty($paymentMethod)) {
            $this->toolBox()->Log('POS')->warning('No se configuro ningun metodo de pago.');
            $result = false;
        }

        $cashMethod = $this->getCashPaymentMethod();
        if ($cashMethod === null || trim($cashMethod) === '') {
            $this->toolBox()->Log('POS')->warning('No se configuro el metodo de pago que se usara para pagos en efectivo.');
            $result = false;
        }

        $defaultDocument = $this->getDefaultDocument();
        if ($defaultDocument->tipodoc === false) {
            $this->toolBox()->Log('POS')->warning('No se configuro el documento predefinido.');
            $result = false;
        }

        if (empty($this->getDenominations())) {
            $this->toolBox()->Log('POS')->warning('No se configuro ninguna moneda, se necesitan para el cierre de arqueo.');
            $result = false;
        }

        return $result;
    }
}
