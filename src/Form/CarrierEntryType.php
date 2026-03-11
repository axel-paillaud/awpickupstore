<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A single carrier row in the carrier settings form.
 * Fields: id_carrier (hidden), require_appointment (checkbox), show_store_picker (checkbox), message (translatable textarea).
 */
class CarrierEntryType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_carrier', HiddenType::class)
            ->add('require_appointment', CheckboxType::class, [
                'label'    => $this->trans('Require appointment', 'Modules.Awpickupstore.Admin'),
                'help'     => $this->trans('Customer must choose a date and time at checkout.', 'Modules.Awpickupstore.Admin'),
                'required' => false,
            ])
            ->add('show_store_picker', CheckboxType::class, [
                'label'    => $this->trans('Show store picker', 'Modules.Awpickupstore.Admin'),
                'help'     => $this->trans('Customer can select a pickup store (event stand) at checkout.', 'Modules.Awpickupstore.Admin'),
                'required' => false,
            ])
            ->add('message', TranslatableType::class, [
                'label'   => $this->trans('Checkout message', 'Modules.Awpickupstore.Admin'),
                'help'    => $this->trans('Displayed below the carrier at checkout (e.g. pickup address and event date). Leave empty for no message.', 'Modules.Awpickupstore.Admin'),
                'type'    => TextareaType::class,
                'options' => [
                    'required' => false,
                    'attr'     => ['rows' => 3],
                ],
            ]);
    }
}
