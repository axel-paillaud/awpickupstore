<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Root form for the carrier settings configuration page.
 * Contains a collection of CarrierEntryType rows, one per active carrier.
 */
class CarrierSettingsFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('carriers', CollectionType::class, [
            'entry_type'   => CarrierEntryType::class,
            'allow_add'    => false,
            'allow_delete' => false,
            'label'        => false,
        ]);
    }
}
