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

use Axelweb\AwPickupStore\Form\ScheduleFormDataProvider;
use Axelweb\AwPickupStore\Repository\PickupStoreRepository;
use Axelweb\AwPickupStore\Service\AppointmentMailService;

class AwPickupStore extends Module
{
    private PickupStoreRepository $repository;
    private AppointmentMailService $mailService;

    public function __construct()
    {
        $this->repository = new PickupStoreRepository();

        $this->name = 'awpickupstore';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.1';
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
            && $this->registerHook('displayAdminOrderMain')
            && $this->registerHook('displayPDFInvoice');

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
            && Configuration::deleteByName('AWPICKUPSTORE_SCHEDULE')
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
            'module-awpickupstore-script',
            'modules/' . $this->name . '/views/js/awpickupstore.js',
            ['position' => 'bottom', 'priority' => 200]
        );
    }

    /**
     * Embed carrier config as JSON before the carrier list.
     * JS reads this and injects message/appointment picker/store picker into each carrier's
     * .carrier-extra-content div.
     */
    public function hookDisplayBeforeCarrier(array $params): string
    {
        $idLang    = (int) $this->context->language->id;
        $minDate   = date('Y-m-d');
        $stores    = null;
        $schedule  = null;
        $configMap = [];

        foreach ($this->repository->getAllCarriersWithConfig($idLang) as $carrier) {
            $entry = $this->buildCarrierEntry($carrier, $minDate, $stores, $schedule, $idLang);
            if ($entry !== null) {
                $configMap[(int) $carrier['id_carrier']] = $entry;
            }
        }

        if (empty($configMap)) {
            return '';
        }

        $this->context->smarty->assign('awpickupstore_config_json', json_encode([
            'carriers'   => $configMap,
            'locale_iso' => $this->context->language->iso_code,
            'i18n'       => [
                'appointment_label'  => $this->trans('Choose your appointment date and time', [], 'Modules.Awpickupstore.Shop'),
                'date_placeholder'   => $this->trans('Select a date and time', [], 'Modules.Awpickupstore.Shop'),
                'store_label'        => $this->trans('Choose a collection point', [], 'Modules.Awpickupstore.Shop'),
                'store_placeholder'  => $this->trans('-- Select a location --', [], 'Modules.Awpickupstore.Shop'),
                'store_required_msg' => $this->trans('Please select a collection point.', [], 'Modules.Awpickupstore.Shop'),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP));

        return $this->display(__FILE__, 'views/templates/hook/before_carrier.tpl');
    }

    /**
     * Build the JSON entry for one carrier, or return null if the carrier has no config.
     * $stores and $schedule are loaded lazily on first need (passed by reference).
     */
    private function buildCarrierEntry(
        array $carrier,
        string $minDate,
        ?array &$stores,
        ?array &$schedule,
        int $idLang
    ): ?array {
        if (!$carrier['message'] && !$carrier['require_appointment'] && !$carrier['show_store_picker']) {
            return null;
        }

        $entry = [
            'message'             => $carrier['message'] ?: null,
            'require_appointment' => (bool) $carrier['require_appointment'],
            'show_store_picker'   => (bool) $carrier['show_store_picker'],
            'min_date'            => $minDate,
        ];

        if ($entry['show_store_picker']) {
            $stores ??= $this->buildStoresForJson($idLang);
            $entry['stores'] = $stores;
        }

        if ($entry['require_appointment']) {
            $schedule ??= (new ScheduleFormDataProvider())->loadSchedule();
            $entry['schedule']         = $schedule;
            $entry['min_delay_hours']  = 2;
        }

        return $entry;
    }

    /**
     * Map active PS stores to the lightweight shape expected by the front-office JSON.
     *
     * @return array<int, array{id: int, name: string, address: string}>
     */
    private function buildStoresForJson(int $idLang): array
    {
        return array_map(static fn (array $s): array => [
            'id'      => (int) ($s['id_store']),
            'name'    => $s['name']    ?? '',
            'address' => trim(($s['address1'] ?? '') . ', ' . ($s['city'] ?? ''), ', '),
        ], $this->repository->getActiveStores($idLang));
    }

    /**
     * Validate appointment / store selection and store it by id_cart during checkout carrier step.
     * actionCarrierProcess fires both on carrier-selection AJAX and on step confirmation.
     * Only validate and save on explicit step confirmation (Continue button).
     */
    public function hookActionCarrierProcess(array $params): void
    {
        if (!Tools::getValue('confirmDeliveryOption')) {
            return;
        }

        $idCarrier = (int) ($params['cart']->id_carrier ?? 0);
        if (!$idCarrier) {
            return;
        }

        $config = $this->repository->getCarrierConfig($idCarrier);
        if (!$config) {
            return;
        }

        $idCart   = (int) $params['cart']->id;
        $datetime = null;
        $idStore  = null;

        // Validate appointment datetime
        if ($config['require_appointment']) {
            $raw = Tools::getValue('awpickupstore_datetime');
            if (empty($raw)) {
                $this->context->controller->errors[] = $this->trans(
                    'Please select an appointment date and time.',
                    [],
                    'Modules.Awpickupstore.Shop'
                );

                return;
            }
            $datetime = date('Y-m-d H:i:s', strtotime($raw));
        }

        // Validate store selection
        if ($config['show_store_picker']) {
            $idStore = (int) Tools::getValue('awpickupstore_store_id');
            if (!$idStore) {
                $this->context->controller->errors[] = $this->trans(
                    'Please select a collection point.',
                    [],
                    'Modules.Awpickupstore.Shop'
                );

                return;
            }
        }

        if ($datetime !== null || $idStore !== null) {
            $this->repository->upsertAppointment($idCart, $datetime, $idStore ?: null);
        }
    }

    /**
     * Attach appointment to the order once it is created, then send confirmation email.
     */
    public function hookActionValidateOrder(array $params): void
    {
        $idCart  = (int) ($params['cart']->id ?? 0);
        $idOrder = (int) ($params['order']->id ?? 0);

        if (!$idCart || !$idOrder) {
            return;
        }

        $this->repository->attachOrderToAppointment($idCart, $idOrder);

        $order    = $params['order'];
        $customer = $params['customer'];
        $idLang   = (int) $customer->id_lang;
        $details  = $this->repository->getAppointmentByOrder($idOrder, $idLang);

        if (!$details) {
            return;
        }

        $datetime  = !empty($details['appointment_datetime'])
            ? date('d/m/Y à H\hi', strtotime($details['appointment_datetime']))
            : null;
        $storeName = $details['store_name'] ?? null;

        (new AppointmentMailService())->send(
            $customer,
            $order->reference,
            $datetime,
            $storeName,
            $idLang,
            (int) $this->context->shop->id
        );
    }

    /**
     * Inject pickup appointment info into the invoice PDF
     */
    public function hookDisplayPDFInvoice(array $params): string
    {
        $idOrder = (int) ($params['object']->id_order ?? 0);
        if (!$idOrder) {
            return '';
        }

        $idLang  = (int) $this->context->language->id;
        $details = $this->repository->getAppointmentByOrder($idOrder, $idLang);

        if (!$details) {
            return '';
        }

        $this->context->smarty->assign([
            'awpickupstore_datetime'   => !empty($details['appointment_datetime'])
                ? date('d/m/Y à H\hi', strtotime($details['appointment_datetime']))
                : null,
            'awpickupstore_store_name' => $details['store_name'] ?? null,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/pdf_invoice.tpl');
    }

    /**
     * Display appointment / store info in the BO order detail page
     */
    public function hookDisplayAdminOrderMain(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if (!$idOrder) {
            return '';
        }

        $idLang  = (int) $this->context->language->id;
        $details = $this->repository->getAppointmentByOrder($idOrder, $idLang);

        if (!$details) {
            return '';
        }

        $this->context->smarty->assign([
            'awpickupstore_datetime'   => !empty($details['appointment_datetime'])
                ? date('d/m/Y à H\hi', strtotime($details['appointment_datetime']))
                : null,
            'awpickupstore_store_name' => $details['store_name'] ?? null,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }
}
