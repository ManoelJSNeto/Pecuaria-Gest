<?php
/**
 * Controlador do Painel Principal / Dashboard
 * PecuáriaGest - Arquitetura Limpa MVC
 */

class DashboardController extends BaseController {

    /**
     * Exibe o dashboard analítico com métricas do rebanho e gráficos
     */
    public function index(): void {
        $this->requireLogin();
        $this->render('dashboard', 'Dashboard', 'dashboard');
    }
}
