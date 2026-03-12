<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminConfigurationController extends FrameworkBundleAdminController
{
    public function index(Request $request): Response
    {
        $carriersHandler = $this->get('axelweb.awpickupstore.form.carrier_settings_form_handler');
        $scheduleHandler = $this->get('axelweb.awpickupstore.form.schedule_form_handler');

        $carriersForm = $carriersHandler->getForm();
        $scheduleForm = $scheduleHandler->getForm();

        $carriersForm->handleRequest($request);
        $scheduleForm->handleRequest($request);

        $activeTab = $request->query->get('tab', 'carriers');

        if ($carriersForm->isSubmitted() && $carriersForm->isValid()) {
            $errors = $carriersHandler->save($carriersForm->getData());

            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('awpickupstore_form_configuration', ['tab' => 'carriers']);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            $activeTab = 'carriers';
        }

        if ($scheduleForm->isSubmitted() && $scheduleForm->isValid()) {
            $errors = $scheduleHandler->save($scheduleForm->getData());

            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('awpickupstore_form_configuration', ['tab' => 'schedule']);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            $activeTab = 'schedule';
        }

        return $this->render('@Modules/awpickupstore/views/templates/admin/form.html.twig', [
            'carriersForm' => $carriersForm->createView(),
            'scheduleForm' => $scheduleForm->createView(),
            'activeTab'    => $activeTab,
        ]);
    }
}
