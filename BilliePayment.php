<?php

namespace BilliePayment;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Model\ModelManager;

/**
 * Main Plugin Class with plugin options.
 * Handles (un-)installation and (de-)activation.
 */
class BilliePayment extends Plugin
{
    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $options = [
            'name'                  => 'billie_payment_after_delivery',
            'description'           => 'Billie Payment After Delivery',
            'action'                => 'BilliePayment',
            'active'                => 1,
            'position'              => 0,
            'additionalDescription' =>
                '<div id="payment_desc">'
                . ' <img src="https://www.billie.io/assets/images/favicons/favicon-16x16.png" width="16" height="16" style="display: inline-block;" />'
                . '  Billie.io - Payment After Delivery'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        $this->autoload();
        $this->createDatabase();
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        // Set to inactive on uninstall to not mess with previous orders!
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);

        if (!$context->keepUserData()) {
            $this->removeDatabase();
        }
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), true);
    }

    /**
     * @param \Shopware\Models\Payment\Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        $models = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $models->flush();
    }

    /**
     * Create the database tables/columns.
     *
     * @return void
     */
    private function createDatabase()
    {
        $allLegalForms = \Billie\Util\LegalFormProvider::all();
        $legalData     = [];
        foreach ($allLegalForms as $legal) {
            $legalData[] = ['key' => $legal['code'], 'value' => $legal['label']];
        }

        $service = $this->container->get('shopware_attribute.crud_service');
        $service->update('s_order_attributes', 'billie_referenceId', 'string');
        $service->update('s_order_attributes', 'billie_state', 'string');
        $service->update('s_order_attributes', 'billie_iban', 'string');
        $service->update('s_order_attributes', 'billie_bic', 'string');
        $service->update('s_user_addresses_attributes', 'billie_registrationNumber', 'string', [
            'label'            => 'Registration Number',
            'translatable'     => true,
            'displayInBackend' => true,
        ]);
        $service->update('s_user_addresses_attributes', 'billie_legalform', 'combobox', [
            'label'            => 'Legalform',
            'translatable'     => true,
            'displayInBackend' => true,
            'arrayStore'       => $legalData
        ]);

        $metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();
        Shopware()->Models()->generateAttributeModels(['s_order_attributes', 's_user_addresses_attributes']);
    }

    /**
     * Remove the database tables.
     *
     * @return void
     */
    private function removeDatabase()
    {
        $service = $this->container->get('shopware_attribute.crud_service');
        $service->delete('s_order_attributes', 'billie_referenceId');
        $service->delete('s_order_attributes', 'billie_state');
        $service->delete('s_order_attributes', 'billie_iban');
        $service->delete('s_order_attributes', 'billie_bic');
        $service->delete('s_user_addresses_attributes', 'billie_registrationNumber');
        $service->delete('s_user_addresses_attributes', 'billie_legalform');
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BillieOverview' => 'onGetBackendController',
            'Enlight_Controller_Front_StartDispatch'                              => 'autoload',
        ];
    }

    /**
     * @return string
     */
    public function onGetBackendController()
    {
        return __DIR__ . '/Controllers/Backend/BillieOverview.php';
    }

    /**
     * Include composer autoloader
     */
    public function autoload()
    {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
    }
}
