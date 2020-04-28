<?php
/**
 * This file is part of EasyPOS plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
namespace FacturaScripts\Plugins\EasyPOS\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Lib\POS\PrintProcessor;
use FacturaScripts\Dinamic\Lib\POS\SalesDataGrid;
use FacturaScripts\Dinamic\Lib\POS\SalesProcessor;
use FacturaScripts\Dinamic\Lib\POS\SessionManager;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\DenominacionMoneda;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Variante;
use function json_encode;

/**
 * Controller to process Point of Sale Operations
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class POS extends Controller
{
    public $cliente;
    public $formaPago;
    public $terminal;
    public $session;

    /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        /** @noinspection PhpParamsInspection */
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        /** Init till session */
        $this->session = new SessionManager($this->user);

        // Get any operations that have to be performed
        $action = $this->request->request->get('action', '');

        /** Run operations before load all data and stop exceution if not nedeed*/
        if ($this->execPreviusAction($action) === false) return;

        /** Init necesary stuff*/
        $this->cliente = new Cliente();
        $this->formaPago = new FormaPago();
        $this->terminal = $this->session->getTerminal();

        /** Run operations after load all data */
        $this->execAfterAction($action);

        /** Set view template*/
        $template = $this->session->isOpen() ? '\POS\SalesScreen' : '\POS\SessionScreen';
        $this->setTemplate($template);
    }

    /**
     * @param string $action
     * @return bool
     */
    private function execPreviusAction(string $action)
    {
        switch ($action) {
            case 'custom-search':
                $this->searchbyText();
                return false;

            case 'barcode-search':
                $this->searchbyBarcode();
                return false;

            case 'load-hostory':
                $result = $this->session->loadHistory();
                $this->response->setContent($result);
                return false;

            case 'recalculate-document':
                $this->recalculateDocument();
                return false;

            default:
                return true;
        }
    }

    private function searchbyText()
    {
        $query = $this->request->request->get('query');
        $target = $this->request->request->get('target');

        switch ($target) {
            case 'customer':
                $result = (new Cliente())->codeModelSearch($query);
                break;

            case 'product':
                $query = str_replace(" ", "%", $query);
                $result = (new Variante())->codeModelSearch($query, 'referencia');
                break;
        }
        $this->response->setContent(json_encode($result));
    }

    private function searchbyBarcode()
    {
        $query = $this->request->request->get('query');
        $result = (new Variante())->codeModelSearch($query, 'referencia');

        $this->response->setContent(json_encode($result));
    }

    private function recalculateDocument()
    {
        $data = $this->request->request->all();
        $modelName = 'FacturaCliente';

        $salesProcessor = new SalesProcessor($modelName, $data);
        $result = $salesProcessor->recalculateDocument();

        $this->response->setContent($result);
    }

    /**
     * Exect action before load data.
     *
     * @param string $action
     */
    private function execAfterAction(string $action)
    {
        switch ($action) {
            case 'close-session':
                $this->closeSession();
                break;

            case 'open-session':
                $idterminal = $this->request->request->get('terminal', '');
                $amount = $this->request->request->get('saldoinicial', 0);
                $this->session->openSession($idterminal, $amount);
                break;

            case 'open-terminal':
                $idterminal = $this->request->request->get('terminal', '');
                $this->terminal = $this->session->getTerminal($idterminal);
                break;

            case 'print-cashup':
                $this->printCashup();
                break;

            case 'save-document':
                $this->processDocument();
                break;

            default:
                break;
        }
    }

    private function closeSession()
    {
        $cash = $this->request->request->get('cash');
        $this->session->closeSession($cash);

        $this->printCashup();
    }

    /**
     * Process sales.
     *
     * @return void
     */
    private function processDocument()
    {
        $data = $this->request->request->all();
        $modelName = $data['tipo-documento'];

        if (false === $this->validateSaveRequest($data)) return;

        $salesProcessor = new SalesProcessor($modelName, $data);
        if ($salesProcessor->saveDocument()) {
            $document = $salesProcessor->getDocument();
            $payments = $salesProcessor->getPaymentsData();

            $this->session->recordOperation($document);
            $this->session->updateCashCount($payments);
            $this->printTicket($document);
            //$this->toolBox()->log()->info(print_r($payments, true));
        }
    }

    /**
     * @param $data
     * @return bool
     */
    private function validateSaveRequest($data)
    {
        if (!$this->permissions->allowUpdate) {
            $this->toolBox()->i18nLog()->warning('not-allowed-modify');
            return false;
        }
        $token = $data['token'];
        if (!empty($token) && $this->multiRequestProtection->tokenExist($token)) {
            $this->toolBox()->i18nLog()->warning('duplicated-request');
            return false;
        }
        return true;
    }

    /**
     * @return void;
     */
    private function printCashup()
    {
        if (PrintProcessor::printCashup($this->session->getArqueo(), $this->empresa)) {
            $values = [
                '%ticket%' => 'Cierre caja',
                '%code%'=>'cashup'
            ];
            $this->toolBox()->i18nLog()->info('printing-ticket', $values);
            return;
        }

        $this->toolBox()->i18nLog()->warning('error-printing-ticket');
    }

    /**
     * @param $document
     * @return void;
     */
    private function printTicket($document)
    {
        if (PrintProcessor::printDocument($document)) {
            $values = [
                '%ticket%' => $document->codigo,
                '%code%'=>$document->modelClassName()
            ];
            $this->toolBox()->i18nLog()->info('printing-ticket', $values);
            return;
        }

        $this->toolBox()->i18nLog()->warning('error-printing-ticket');
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'point-of-sale';
        $pagedata['menu'] = 'point-of-sale';
        $pagedata['icon'] = 'fas fa-shopping-cart';
        $pagedata['showonmenu'] = true;

        return $pagedata;
    }

    /**
     * Returns the cash payment method ID.
     *
     * @return string
     */
    public function getCashPaymentMethod()
    {
        return $this->toolBox()->appSettings()->get('pointofsale', 'fpagoefectivo');
    }

    /**
     * Returns headers and columns available by user permissions.
     *
     * @return string
     */
    public function getDataGridHeaders()
    {
        return SalesDataGrid::getDataGridHeaders($this->user);
    }

    /**
     * Returns all available denominations.
     *
     * @return array
     */
    public function getDenominations()
    {
        return (new DenominacionMoneda)->all([], ['valor' => 'ASC']);
    }

    /**
     * Returns a random token to use as transaction id and avoid multisubmit request.
     *
     * @return string
     */
    public function getRandomToken()
    {
        return $this->multiRequestProtection->newToken();
    }
}
