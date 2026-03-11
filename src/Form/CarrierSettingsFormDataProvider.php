<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Form;

use Axelweb\AwPickupStore\Repository\PickupStoreRepository;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Provides carrier settings data to the form and persists submitted data.
 * Reads/writes directly via PickupStoreRepository — no ps_configuration involved.
 */
class CarrierSettingsFormDataProvider implements FormDataProviderInterface
{
    private PickupStoreRepository $repository;

    public function __construct(PickupStoreRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Returns all active carriers with their current pickup settings.
     * The 'name' key in each entry is for Twig display only (not a form field).
     *
     * @return array{carriers: array<int, array{id_carrier: int, name: string, require_appointment: bool, message: string}>}
     */
    public function getData(): array
    {
        $carriers = $this->repository->getAllCarriersWithConfig();

        return [
            'carriers' => array_map(static function (array $row): array {
                return [
                    'id_carrier'          => (int) $row['id_carrier'],
                    'name'                => (string) ($row['name'] ?? ''),
                    'require_appointment' => (bool) $row['require_appointment'],
                    'message'             => (string) ($row['message'] ?? ''),
                ];
            }, $carriers),
        ];
    }

    /**
     * Persists the submitted carrier settings.
     * Returns an array of error messages (empty on success).
     *
     * @param array{carriers?: array<int, array{id_carrier: mixed, require_appointment: mixed, message: mixed}>} $data
     *
     * @return string[]
     */
    public function setData(array $data): array
    {
        foreach ($data['carriers'] ?? [] as $entry) {
            $this->repository->saveCarrierConfig(
                (int) $entry['id_carrier'],
                (bool) ($entry['require_appointment'] ?? false),
                (string) ($entry['message'] ?? '')
            );
        }

        return [];
    }
}
