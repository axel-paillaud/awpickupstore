<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Repository;

class PickupStoreRepository
{
    private \Db $db;

    public function __construct()
    {
        $this->db = \Db::getInstance();
    }

    /**
     * Get carrier configuration (require_appointment, show_store_picker).
     * Returns null if the carrier is not configured in this module.
     *
     * @return array{require_appointment: string, show_store_picker: string}|null
     */
    public function getCarrierConfig(int $idCarrier): ?array
    {
        $row = $this->db->getRow(
            'SELECT `require_appointment`, `show_store_picker`
             FROM `' . _DB_PREFIX_ . 'awpickupstore_carrier`
             WHERE `id_carrier` = ' . $idCarrier
        );

        return $row ?: null;
    }

    /**
     * Return all active, non-deleted carriers with their awpickupstore config.
     * Used by the BO config form — no message column (loaded per-carrier via getAllCarrierMessages).
     *
     * @return array<int, array{id_carrier: string, name: string, require_appointment: string|null, show_store_picker: string|null}>
     */
    public function getAllCarriersBasic(): array
    {
        return $this->db->executeS(
            'SELECT c.`id_carrier`, c.`name`,
                    apc.`require_appointment`, apc.`show_store_picker`
             FROM `' . _DB_PREFIX_ . 'carrier` c
             LEFT JOIN `' . _DB_PREFIX_ . 'awpickupstore_carrier` apc
                ON c.`id_carrier` = apc.`id_carrier`
             WHERE c.`deleted` = 0
             ORDER BY c.`name` ASC'
        ) ?: [];
    }

    /**
     * Return all active, non-deleted carriers with their config and the message
     * in the given language. Used by hookDisplayBeforeCarrier (front-office).
     *
     * @return array<int, array{id_carrier: string, name: string, require_appointment: string|null, show_store_picker: string|null, message: string|null}>
     */
    public function getAllCarriersWithConfig(int $idLang): array
    {
        return $this->db->executeS(
            'SELECT c.`id_carrier`, c.`name`,
                    apc.`require_appointment`, apc.`show_store_picker`,
                    apcl.`message`
             FROM `' . _DB_PREFIX_ . 'carrier` c
             LEFT JOIN `' . _DB_PREFIX_ . 'awpickupstore_carrier` apc
                ON c.`id_carrier` = apc.`id_carrier`
             LEFT JOIN `' . _DB_PREFIX_ . 'awpickupstore_carrier_lang` apcl
                ON apc.`id_carrier` = apcl.`id_carrier` AND apcl.`id_lang` = ' . $idLang . '
             WHERE c.`deleted` = 0
             ORDER BY c.`name` ASC'
        ) ?: [];
    }

    /**
     * Return all messages for a carrier, keyed by id_lang.
     * Used to populate the BO form with per-language textareas.
     *
     * @return array<int, string>
     */
    public function getAllCarrierMessages(int $idCarrier): array
    {
        $rows = $this->db->executeS(
            'SELECT `id_lang`, `message`
             FROM `' . _DB_PREFIX_ . 'awpickupstore_carrier_lang`
             WHERE `id_carrier` = ' . $idCarrier
        ) ?: [];

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id_lang']] = (string) $row['message'];
        }

        return $result;
    }

    /**
     * Return all active stores with their name and address in the given language.
     * Used to populate the store picker dropdown at checkout.
     *
     * @return array<int, array{id_store: string, name: string|null, city: string|null, postcode: string|null, address1: string|null}>
     */
    public function getActiveStores(int $idLang): array
    {
        return $this->db->executeS(
            'SELECT s.`id_store`, sl.`name`, s.`city`, s.`postcode`, sl.`address1`
             FROM `' . _DB_PREFIX_ . 'store` s
             LEFT JOIN `' . _DB_PREFIX_ . 'store_lang` sl
                ON s.`id_store` = sl.`id_store` AND sl.`id_lang` = ' . $idLang . '
             WHERE s.`active` = 1
             ORDER BY sl.`name` ASC'
        ) ?: [];
    }

    /**
     * Insert or update an appointment for a given cart.
     * $datetime may be null for store-only selections (no appointment required).
     */
    public function upsertAppointment(int $idCart, ?string $datetime, ?int $idStore = null): bool
    {
        $storeValue    = $idStore    !== null ? $idStore                      : 'NULL';
        $datetimeValue = $datetime   !== null ? '\'' . pSQL($datetime) . '\'' : 'NULL';

        return (bool) $this->db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'awpickupstore_appointment`
                (`id_cart`, `id_order`, `id_store`, `appointment_datetime`)
             VALUES (' . $idCart . ', 0, ' . $storeValue . ', ' . $datetimeValue . ')
             ON DUPLICATE KEY UPDATE
                `id_store`             = ' . $storeValue . ',
                `appointment_datetime` = ' . $datetimeValue
        );
    }

    /**
     * Attach an order ID to an appointment stored by cart ID.
     */
    public function attachOrderToAppointment(int $idCart, int $idOrder): bool
    {
        return (bool) $this->db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'awpickupstore_appointment`
             SET `id_order` = ' . $idOrder . '
             WHERE `id_cart` = ' . $idCart . ' AND `id_order` = 0'
        );
    }

    /**
     * Get appointment details for a given order: datetime and store name.
     * Returns null if no appointment row exists for this order.
     *
     * @return array{appointment_datetime: string|null, store_name: string|null}|null
     */
    public function getAppointmentByOrder(int $idOrder, int $idLang = 0): ?array
    {
        $row = $this->db->getRow(
            'SELECT a.`appointment_datetime`, sl.`name` AS `store_name`
             FROM `' . _DB_PREFIX_ . 'awpickupstore_appointment` a
             LEFT JOIN `' . _DB_PREFIX_ . 'store_lang` sl
                ON a.`id_store` = sl.`id_store` AND sl.`id_lang` = ' . $idLang . '
             WHERE a.`id_order` = ' . $idOrder
        );

        return $row ?: null;
    }

    /**
     * Save (upsert) carrier configuration and per-language messages.
     * If all fields are empty/false, removes the carrier row and all its messages.
     *
     * @param array<int, string> $messages Keyed by id_lang
     */
    public function saveCarrierConfig(
        int $idCarrier,
        bool $requireAppointment,
        bool $showStorePicker,
        array $messages
    ): bool {
        $messages = array_filter(
            $messages,
            static fn (string $msg): bool => trim($msg) !== ''
        );

        if (!$requireAppointment && !$showStorePicker && empty($messages)) {
            $this->db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'awpickupstore_carrier_lang`
                 WHERE `id_carrier` = ' . $idCarrier
            );

            return (bool) $this->db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'awpickupstore_carrier`
                 WHERE `id_carrier` = ' . $idCarrier
            );
        }

        $this->db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'awpickupstore_carrier`
                (`id_carrier`, `require_appointment`, `show_store_picker`)
             VALUES (' . $idCarrier . ', ' . (int) $requireAppointment . ', ' . (int) $showStorePicker . ')
             ON DUPLICATE KEY UPDATE
                `require_appointment` = ' . (int) $requireAppointment . ',
                `show_store_picker`   = ' . (int) $showStorePicker
        );

        $this->db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'awpickupstore_carrier_lang`
             WHERE `id_carrier` = ' . $idCarrier
        );

        foreach ($messages as $idLang => $message) {
            $message = trim($message);
            if ($message === '') {
                continue;
            }
            $this->db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'awpickupstore_carrier_lang`
                    (`id_carrier`, `id_lang`, `message`)
                 VALUES (' . $idCarrier . ', ' . (int) $idLang . ', \'' . pSQL($message) . '\')'
            );
        }

        return true;
    }
}
