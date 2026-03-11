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
     * Get carrier configuration (message + require_appointment).
     * Returns null if the carrier is not configured in this module.
     *
     * @return array{require_appointment: string, message: string|null}|null
     */
    public function getCarrierConfig(int $idCarrier): ?array
    {
        $row = $this->db->getRow(
            'SELECT `require_appointment`, `message`
             FROM `' . _DB_PREFIX_ . 'awpickupstore_carrier`
             WHERE `id_carrier` = ' . $idCarrier
        );

        return $row ?: null;
    }

    /**
     * Insert or update an appointment for a given cart.
     * $datetime must be a valid 'Y-m-d H:i:s' string (caller's responsibility).
     */
    public function upsertAppointment(int $idCart, string $datetime): bool
    {
        return (bool) $this->db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'awpickupstore_appointment`
                (`id_cart`, `id_order`, `appointment_datetime`)
             VALUES (' . $idCart . ', 0, \'' . pSQL($datetime) . '\')
             ON DUPLICATE KEY UPDATE `appointment_datetime` = \'' . pSQL($datetime) . '\''
        );
    }

    /**
     * Attach an order ID to an appointment stored by cart ID.
     * Called once the order is created (actionValidateOrder).
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
     * Get the appointment datetime for a given order, or null if none.
     */
    public function getAppointmentByOrder(int $idOrder): ?string
    {
        $value = $this->db->getValue(
            'SELECT `appointment_datetime`
             FROM `' . _DB_PREFIX_ . 'awpickupstore_appointment`
             WHERE `id_order` = ' . $idOrder
        );

        return $value ?: null;
    }

    /**
     * Return all active, non-deleted carriers joined with their awpickupstore config (if any).
     *
     * @return array<int, array{id_carrier: string, name: string, require_appointment: string|null, message: string|null}>
     * Note: carrier name is stored directly in ps_carrier (not multilingual). ps_carrier_lang only holds `delay`.
     */
    public function getAllCarriersWithConfig(): array
    {
        return $this->db->executeS(
            'SELECT c.`id_carrier`, c.`name`,
                    apc.`require_appointment`, apc.`message`
             FROM `' . _DB_PREFIX_ . 'carrier` c
             LEFT JOIN `' . _DB_PREFIX_ . 'awpickupstore_carrier` apc
                ON c.`id_carrier` = apc.`id_carrier`
             WHERE c.`deleted` = 0
             ORDER BY c.`name` ASC'
        ) ?: [];
    }

    /**
     * Save (upsert) carrier configuration.
     * If both message and require_appointment are empty/false, removes the row.
     */
    public function saveCarrierConfig(int $idCarrier, bool $requireAppointment, string $message): bool
    {
        $message = trim($message);

        if (!$requireAppointment && $message === '') {
            return (bool) $this->db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'awpickupstore_carrier`
                 WHERE `id_carrier` = ' . $idCarrier
            );
        }

        return (bool) $this->db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'awpickupstore_carrier`
                (`id_carrier`, `require_appointment`, `message`)
             VALUES (' . $idCarrier . ', ' . (int) $requireAppointment . ', \'' . pSQL($message) . '\')
             ON DUPLICATE KEY UPDATE
                `require_appointment` = ' . (int) $requireAppointment . ',
                `message` = \'' . pSQL($message) . '\''
        );
    }
}
