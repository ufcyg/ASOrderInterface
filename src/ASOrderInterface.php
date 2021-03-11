<?php declare(strict_types=1);

namespace ASOrderInterface;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/*

This plugin will check regularly through a scheduled task for open orders and submit those to the logistics partner 
defined in the plugins configuration. Another scheduled task will check regularly for responses of the logistics partner on
the remote sFTP server, read those answers and adjust data accordingly.
This means:
- Status of order processing, aka processing in remote warehouse management software / end of packaging
- Delivery Status of placed orders

In case an error is reported by the logistics partner an eMail notification will be dispatched to an unrestricted amount of people
defined in the plugins configuration. Also normally local deleted files after evaluation of data will be kept if an error of any 
kind occures.

*/
class ASOrderInterface extends Plugin
{
    /** @inheritDoc */
    public function install(InstallContext $installContext): void
    {
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive', 0777, true);
        }
        
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/Articlebase')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/Articlebase', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/SubmittedOrders')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/SubmittedOrders', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/Artikel_Error')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/Artikel_Error', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/Artikel_Error')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/Artikel_Error', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/RM_WA')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/RM_WA', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/RM_WA')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/RM_WA', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/RM_WE')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/RM_WE', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/RM_WE')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/RM_WE', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/Bestand')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/Bestand', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/Bestand')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/Bestand', 0777, true);
        }
    }

    /** @inheritDoc */
    public function postInstall(InstallContext $installContext): void
    {
    }

    /** @inheritDoc */
    public function update(UpdateContext $updateContext): void
    {
    }

    /** @inheritDoc */
    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    /** @inheritDoc */
    public function activate(ActivateContext $activateContext): void
    {
    }

    /** @inheritDoc */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
    }

    /** @inheritDoc */
    public function uninstall(UninstallContext $context): void
    {
        if ($context->keepUserData()) {
            parent::uninstall($context);

            return;
        }

        // Remove all traces of the plugin
        $dir = '../custom/plugins/ASOrderInterface/InterfaceData';
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it,
             RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) 
        {
            if ($file->isDir())
            {
                rmdir($file->getRealPath());
            }
            else 
            {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);

        $connection = $this->container->get(Connection::class);

        $connection->executeUpdate('DROP TABLE IF EXISTS `as_stock_qs`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `as_cancelled_confirmation`');

        // this will remain for some more versions to be sure every table is dopped everywhere
        $connection->executeUpdate('DROP TABLE IF EXISTS `as_parcel_tracking`');
        parent::uninstall($context);
    }
}