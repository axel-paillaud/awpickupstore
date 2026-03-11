<?php
/**
 * 2007-2024 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of Axelweb
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Axelweb\AwPickupStore\Repository\PickupStoreRepository;

class AwPickupStore extends Module
{
    private PickupStoreRepository $repository;

    public function __construct()
    {
        $this->repository = new PickupStoreRepository();

        $this->name = 'awpickupstore';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Axelweb';
        $this->need_instance = 1;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Pickup Store', [], 'Modules.Awpickupstore.Admin');
        $this->description = $this->trans('Prestashop module to add temporary in-store pickup options and specify an appointment date.', [], 'Modules.Awpickupstore.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], 'Modules.Awpickupstore.Admin');

        $this->ps_versions_compliancy = [
            'min' => '8.0',
            'max' => _PS_VERSION_,
        ];
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install(): bool
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $installed = parent::install()
            && $this->installDb()
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayCarrierExtraContent')
            && $this->registerHook('actionCarrierProcess')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('displayAdminOrderMain');

        // Prevent 'Unable to generate a URL for the named route [...]' error,
        // clear Symfony cache
        if ($installed) {
            Tools::clearSf2Cache();
        }

        return $installed;
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->uninstallDb();
    }

    protected function installDb(): bool
    {
        $file = __DIR__ . '/sql/install.php';

        return is_file($file) ? (bool) require $file : false;
    }

    protected function uninstallDb(): bool
    {
        $file = __DIR__ . '/sql/uninstall.php';

        return is_file($file) ? (bool) require $file : false;
    }

    /**
     * Redirect to the module symfony configuration page
     *
     * @return void
     */
    public function getContent(): void
    {
        $route = $this->get('router')->generate('awpickupstore_form_configuration');
        Tools::redirectAdmin($route);
    }

    /**
     * Register CSS and JS on the checkout page only
     */
    public function hookActionFrontControllerSetMedia(): void
    {
        if ($this->context->controller->php_self !== 'order') {
            return;
        }

        $this->context->controller->registerStylesheet(
            'module-awpickupstore-style',
            'modules/' . $this->name . '/views/css/awpickupstore.css',
            ['media' => 'all', 'priority' => 200]
        );

        $this->context->controller->registerJavascript(
            'module-awpickupstore-script',
            'modules/' . $this->name . '/views/js/awpickupstore.js',
            ['position' => 'bottom', 'priority' => 200]
        );
    }

    /**
     * Display message and/or appointment picker when a carrier is selected at checkout
     */
    public function hookDisplayCarrierExtraContent(array $params): string
    {
        $idCarrier = (int) ($params['carrier']['id'] ?? 0);
        if (!$idCarrier) {
            return '';
        }

        $config = $this->repository->getCarrierConfig($idCarrier);

        if (!$config) {
            return '';
        }

        $this->context->smarty->assign([
            'awpickupstore_message'             => $config['message'],
            'awpickupstore_require_appointment' => (bool) $config['require_appointment'],
            'awpickupstore_id_carrier'          => $idCarrier,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/carrier_extra_content.tpl');
    }

    /**
     * Validate appointment and store it by id_cart during checkout carrier step
     */
    public function hookActionCarrierProcess(array $params): void
    {
        $idCarrier = (int) ($params['cart']->id_carrier ?? 0);
        if (!$idCarrier) {
            return;
        }

        $config = $this->repository->getCarrierConfig($idCarrier);

        if (!$config || !$config['require_appointment']) {
            return;
        }

        $date = Tools::getValue('awpickupstore_date');
        $time = Tools::getValue('awpickupstore_time');

        if (empty($date) || empty($time)) {
            $this->context->controller->errors[] = $this->trans(
                'Please select an appointment date and time.',
                [],
                'Modules.Awpickupstore.Shop'
            );

            return;
        }

        $datetime = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));
        $idCart   = (int) $params['cart']->id;

        $this->repository->upsertAppointment($idCart, $datetime);
    }

    /**
     * Attach appointment to the order once it is created
     */
    public function hookActionValidateOrder(array $params): void
    {
        $idCart  = (int) ($params['cart']->id ?? 0);
        $idOrder = (int) ($params['order']->id ?? 0);

        if (!$idCart || !$idOrder) {
            return;
        }

        $this->repository->attachOrderToAppointment($idCart, $idOrder);
    }

    /**
     * Display appointment info in the BO order detail page
     */
    public function hookDisplayAdminOrderMain(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if (!$idOrder) {
            return '';
        }

        $datetime = $this->repository->getAppointmentByOrder($idOrder);

        if (!$datetime) {
            return '';
        }

        $this->context->smarty->assign('awpickupstore_appointment_datetime', $datetime);

        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }
}
