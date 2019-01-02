<?php
namespace PSFS\controller;

use PSFS\controller\base\Admin;

/**
 * Class LogController
 * @package PSFS\controller
 */
class LogController extends Admin
{
    /**
     * Servicio que muestra los logs del sistema
     * @GET
     * @route /admin/logs
     * @label VIsor de logs del sistema
     * @visible false
     * @return string|null
     */
    public function logs()
    {
        $log = t("Selecciona un fichero de log");
        $logs = $this->srv->getLogFiles();

        asort($logs);
        return $this->render("logs.html.twig", array(
            "logs" => $logs,
            "log" => $log,
        ));
    }

    /**
     * @POST
     * @route /admin/logs
     * @visible false
     * @return string
     */
    public function showLogs()
    {
        $logs = $this->srv->getLogFiles();
        $selected = $this->getRequest()->get("log");
        list($log, $monthOpen) = $this->srv->formatLogFile($selected);
        asort($logs);
        return $this->render("logs.html.twig", array(
            "logs" => $logs,
            "log" => $log,
            "selected" => $selected,
            "month_open" => $monthOpen,
        ));
    }
}