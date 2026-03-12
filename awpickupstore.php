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
        $this->tab = 'shipping_logistics';
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
            && $this->registerHook('displayBeforeCarrier')
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
            'module-awpickupstore-flatpickr',
            'modules/' . $this->name . '/views/css/lib/flatpickr.min.css',
            ['media' => 'all', 'priority' => 190]
        );
        $this->context->controller->registerStylesheet(
            'module-awpickupstore-style',
            'modules/' . $this->name . '/views/css/awpickupstore.css',
            ['media' => 'all', 'priority' => 200]
        );

        $this->context->controller->registerJavascript(
            'module-awpickupstore-flatpickr',
            'modules/' . $this->name . '/views/js/lib/flatpickr.min.js',
            ['position' => 'bottom', 'priority' => 190]
        );
        $this->context->controller->registerJavascript(
            'module-awpickupstore-flatpickr-fr',
            'modules/' . $this->name . '/views/js/lib/flatpickr.l10n.fr.js',
            ['position' => 'bottom', 'priority' => 195]
        );
        $this->context->controller->registerJavascript(
            'module-awpickupstore-store',
            'modules/' . $this->name . '/views/js/awpickupstore-store.js',
            ['position' => 'bottom', 'priority' => 196]
        );
        $this->context->controller->registerJavascript(
            'module-awpickupstore-datepicker',
            'modules/' . $this->name . '/views/js/awpickupstore-datepicker.js',
            ['position' => 'bottom', 'priority' => 197]
        );
        $this->context->controller->registerJavascript(
            'module-awpickupstore-script',
            'modules/' . $this->name . '/views/js/awpickupstore.js',
            ['position' => 'bottom', 'priority' => 200]
        );
    }

    /**
     * Embed carrier config as JSON before the carrier list.
     * JS reads this and injects message/appointment picker into each carrier's .carrier-extra-content div.
     */
    public function hookDisplayBeforeCarrier(array $params): string
    {
        $idLang  = (int) $this->context->language->id;
        $carriers = $this->repository->getAllCarriersWithConfig($idLang);
        $configMap = [];
        $minDate   = date('Y-m-d');

        foreach ($carriers as $carrier) {
            if (!$carrier['message'] && !$carrier['require_appointment'] && !$carrier['show_store_picker']) {
                continue;
            }
            $configMap[(int) $carrier['id_carrier']] = [
                'message'             => $carrier['message'] ?: null,
                'require_appointment' => (bool) $carrier['require_appointment'],
                'show_store_picker'   => (bool) $carrier['show_store_picker'],
                'min_date'            => $minDate,
            ];
        }

        if (empty($configMap)) {
            return '';
        }

        $this->context->smarty->assign('awpickupstore_config_json', json_encode([
            'carriers'   => $configMap,
            'locale_iso' => $this->context->language->iso_code,
            'i18n'       => [
                'appointment_label' => $this->trans('Choose your appointment date and time', [], 'Modules.Awpickupstore.Shop'),
                'date_placeholder'  => $this->trans('Select a date and time', [], 'Modules.Awpickupstore.Shop'),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP));

        return $this->display(__FILE__, 'views/templates/hook/before_carrier.tpl');
    }

    /**
     * Validate appointment and store it by id_cart during checkout carrier step
     */
    public function hookActionCarrierProcess(array $params): void
    {
        // actionCarrierProcess fires both on carrier-selection AJAX and on step confirmation.
        // Only validate and save on explicit step confirmation (Continue button).
        if (!Tools::getValue('confirmDeliveryOption')) {
            return;
        }

        $idCarrier = (int) ($params['cart']->id_carrier ?? 0);
        if (!$idCarrier) {
            return;
        }

        $config = $this->repository->getCarrierConfig($idCarrier);

        if (!$config || !$config['require_appointment']) {
            return;
        }

        $datetime = Tools::getValue('awpickupstore_datetime');

        if (empty($datetime)) {
            $this->context->controller->errors[] = $this->trans(
                'Please select an appointment date and time.',
                [],
                'Modules.Awpickupstore.Shop'
            );

            return;
        }

        $datetime = date('Y-m-d H:i:s', strtotime($datetime));
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

        $this->context->smarty->assign(
            'awpickupstore_appointment_datetime',
            date('d/m/Y à H\hi', strtotime($datetime))
        );

        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }
}
