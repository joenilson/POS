<?php

namespace FacturaScripts\Plugins\POS\Lib;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\OperacionPausada;
use FacturaScripts\Dinamic\Model\OrdenPuntoVenta;
use FacturaScripts\Dinamic\Model\SesionPuntoVenta;
use FacturaScripts\Dinamic\Model\TerminalPuntoVenta;
use FacturaScripts\Dinamic\Model\User;

class PointOfSaleSession
{
    /**
     * @var OrdenPuntoVenta
     */
    protected $lastOrder;

    /**
     * @var SesionPuntoVenta
     */
    protected $session;

    /**
     * @var TerminalPuntoVenta
     */
    protected $terminal;

    /**
     * @var User
     */
    protected $user;

    /**
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->terminal = new TerminalPuntoVenta();
        $this->session = new SesionPuntoVenta();

        $this->loadSession($user->nick);
    }

    protected function loadSession(string $nick): void
    {
        if (false === $this->session->getUserSession($nick)) {
            return;
        }

        $this->loadTerminal($this->session->idterminal);
    }

    protected function loadTerminal(string $code): bool
    {
        if (false === $this->terminal->loadFromCode($code)) {
            ToolBox::i18nLog()->warning('cash-register-not-found');
            return false;
        }

        return true;
    }

    /**
     * Return current user SesionPuntoVenta.
     *
     * @return SesionPuntoVenta
     */
    public function getSession(): SesionPuntoVenta
    {
        return $this->session;
    }

    /**
     * Return current session terminal.
     *
     * @param string $id
     * @return TerminalPuntoVenta
     */
    public function getTerminal(string $id = ''): TerminalPuntoVenta
    {
        if (false === empty($id) && false === $this->isOpen()) {
            $this->loadTerminal($id);
        }

        return $this->terminal;
    }

    /**
     * Return true if session is open, false otherwise.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->session->abierto && $this->session->nickusuario;
    }

    /**
     * Get POS view if session is allready open or Open View.
     *
     * @return string
     */
    public function getView(): string
    {
        return $this->isOpen() ? '/Block/POS/Main' : '/Block/POS/Login';
    }

    public function openSession(string $terminal, float $amount = 0.0)
    {
        if (true === $this->session->abierto) {
            ToolBox::i18nLog()->info('till-session-allready-opened');
            return;
        }

        if (false === $this->loadTerminal($terminal)) {
            return;
        }

        if ($this->session->openSession($this->terminal, $amount, $this->user->nick)) {
            $params = [
                '%terminalName%' => $this->terminal->nombre,
                '%userNickname%' => $this->user->nick,
            ];
            ToolBox::i18nLog()->info('till-session-opened', $params);
            ToolBox::i18nLog()->info('cashup-total', ['%amount%' => $amount]);

            $this->terminal->disponible = false;
            $this->terminal->save();

            return;
        }

        ToolBox::i18nLog()->info('error');
    }

    /**
     * Close current session.
     *
     * @return void
     */
    public function closeSession(array $cash)
    {
        if (false === $this->session->abierto) {
            ToolBox::i18nLog()->info('there-is-no-open-till-session');
            return;
        }

        $this->session->abierto = false;
        $this->session->fechafin = date('d-m-Y');
        $this->session->horafin = date('H:i:s');

        $total = 0.0;
        foreach ($cash as $value => $count) {
            $total += (float)$value * (float)$count;
        }

        ToolBox::i18nLog()->info('cashup-total', ['%amount%' => $total]);
        $this->session->saldocontado = $total;
        $this->session->conteo = json_encode($cash);

        if ($this->session->save()) {
            $this->terminal->disponible = true;
            $this->terminal->save();
            $this->open = false;
        }
    }

    public function completePausedOrder(string $code): bool
    {
        return $this->session->completePausedOrder($code);
    }

    public function deletePausedOrder(string $code): bool
    {
        return $this->session->deletePausedOrder($code);
    }

    public function getLastOrder(): OrdenPuntoVenta
    {
        return $this->lastOrder;
    }

    public function getOrder($code): OrdenPuntoVenta
    {
        return $this->session->getOrder($code);
    }

    public function getOrders(): array
    {
        return $this->session->getOrders();
    }

    public function getPausedOrder($code): OperacionPausada
    {
        return $this->session->getPausedOrder($code);
    }

    public function getPausedOrders(): array
    {
        return $this->session->getPausedOrders();
    }

    /**
     * @param BusinessDocument $document
     * @return bool
     */
    public function saveOrder(BusinessDocument $document): bool
    {
        $this->lastOrder = new OrdenPuntoVenta();

        if (false === $this->session->saveOrder($document, $this->lastOrder)) {
            return false;
        }

        $this->completePausedOrder($document->idpausada);

        return true;
    }

    /**
     * Replace current user by the given one.
     *
     * @param User $user
     * @return void
     */
    public function updateUser(User $user)
    {
        $this->user = $user;

        $this->session->nickusuario = $this->user->nick;
        $this->session->save();
    }

    public function updateCashAmount(float $amount = 0)
    {
        $this->session->saldoesperado += $amount;
        $this->session->save();
    }
}
