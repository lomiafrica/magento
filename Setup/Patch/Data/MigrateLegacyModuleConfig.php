<?php

namespace Lomi\Payments\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Migrates config paths and payment method codes from the legacy plugin identifiers.
 */
class MigrateLegacyModuleConfig implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var string */
    private const LEGACY_CONFIG_PREFIX = 'payment/pstk_paystack/';

    /** @var string */
    private const NEW_CONFIG_PREFIX = 'payment/lomi/';

    /** @var string */
    private const LEGACY_METHOD = 'pstk_paystack';

    /** @var string */
    private const NEW_METHOD = 'lomi';

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): void
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();
        $conn = $setup->getConnection();
        $configTable = $setup->getTable('core_config_data');

        if ($conn->isTableExists($configTable)) {
            $select = $conn->select()->from($configTable)->where('path LIKE ?', self::LEGACY_CONFIG_PREFIX . '%');
            foreach ($conn->fetchAll($select) as $row) {
                $newPath = str_replace(self::LEGACY_CONFIG_PREFIX, self::NEW_CONFIG_PREFIX, $row['path']);
                $exists = (int) $conn->fetchOne(
                    'SELECT COUNT(*) FROM ' . $configTable . ' WHERE scope = ? AND scope_id = ? AND path = ?',
                    [$row['scope'], $row['scope_id'], $newPath]
                );
                if ($exists === 0) {
                    $conn->insert($configTable, [
                        'scope' => $row['scope'],
                        'scope_id' => $row['scope_id'],
                        'path' => $newPath,
                        'value' => $row['value'],
                    ]);
                }
            }
        }

        $orderPayment = $setup->getTable('sales_order_payment');
        if ($conn->isTableExists($orderPayment)) {
            $conn->update($orderPayment, ['method' => self::NEW_METHOD], ['method = ?' => self::LEGACY_METHOD]);
        }

        $quotePayment = $setup->getTable('quote_payment');
        if ($conn->isTableExists($quotePayment)) {
            $conn->update($quotePayment, ['method' => self::NEW_METHOD], ['method = ?' => self::LEGACY_METHOD]);
        }

        $setup->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
