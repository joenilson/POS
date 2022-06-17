<?php
/**
 * This file is part of POS plugin for FacturaScripts
 * Copyright (C) 2022 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */

namespace FacturaScripts\Plugins\POS\Lib;

use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\DenominacionMoneda;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\TerminalPuntoVenta;

trait PointOfSaleTrait
{
    /**
     * @var PointOfSaleSession
     */
    protected $session;

    /**
     * Returns the cash payment method ID.
     *
     * @return string
     */
    public function getCashPaymentMethod(): string
    {
        return $this->getSetting('fpagoefectivo') ?: '';
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
     * @return string
     */
    public function getDefaultDocument(): string
    {
        return $this->getTerminal()->defaultdocument ?: $this->getSetting('defaultdocument');
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
     * Returns headers and columns available by user permissions.
     *
     * @return array
     */
    public function getFormHeaders(): array
    {
        return PointOfSaleForms::getFormsGrid($this->user);
    }

    /**
     * Return some products for initial view
     *
     * @return array
     */
    public function getHomeProducts(): array
    {
        $product = new PointOfSaleProduct();

        return $product->advancedSearch('', [], $this->getTerminal()->codalmacen);
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
     * Returns all available payment methods.
     *
     * @return FormaPago[]
     */
    public function getPaymentMethods(): array
    {
        $formasPago = [];

        $formasPagoCodeList = explode('|', $this->getSetting('formaspago'));
        foreach ($formasPagoCodeList as $value) {
            $formasPago[] = (new FormaPago())->get($value);
        }

        return $formasPago;
    }

    /**
     * @param $document
     * @return void;
     */
    protected function printVoucher($document)
    {
        $message = PointOfSalePrinter::salesTicket($document, $this->getTerminal()->anchopapel);

        $this->toolBox()->log()->info($message);
    }

    /**
     * Print closing voucher.
     *
     * @return void;
     */
    protected function printClosingVoucher()
    {
        $message = PointOfSalePrinter::cashupTicket($this->session->getSession(), $this->empresa,
            $this->getTerminal()->anchopapel);

        $this->toolBox()->log()->info($message);
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
     * Return Current Session Storage Object
     *
     * @return PointOfSaleStorage
     */
    protected function getStorage(): PointOfSaleStorage
    {
        return $this->session->getStorage();
    }

    /**
     * Get current user session terminal.
     *
     * @return TerminalPuntoVenta
     */
    public function getTerminal(): TerminalPuntoVenta
    {
        return $this->getSession()->getTerminal();
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

        $cashMethod = $this->getCashPaymentMethod();
        if ($cashMethod === null || trim($cashMethod) === '') {
            $this->toolBox()->Log()->warning('No se configuro el metodo de pago que se usara para pagos en efectivo');
            $result = false;
        }

        $defaultDocument = $this->getDefaultDocument();
        if ($defaultDocument === null || trim($defaultDocument) === '') {
            $this->toolBox()->Log()->warning('No se configuro el documento predefinido');
            $result = false;
        }

        $paymentMethod = $this->getPaymentMethods();
        if (empty($paymentMethod)) {
            $this->toolBox()->Log()->warning('No se configuro ningun metodo de pago disponible.');
            $result = false;
        }

        if (empty($this->getDenominations())) {
            $this->toolBox()->Log()->warning('No se configuro ninguna moneda, se necesitan para el cierre de arqueo.');
            $result = false;
        }

        return $result;
    }
}