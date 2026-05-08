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
    private const LEGACY_PREFIX_HEX = '7061796d656e742f7073746b5f706179737461636b2f';

    /** @var string */
    private const NEW_CONFIG_PREFIX = 'payment/lomi/';

    /** @var string */
    private const LEGACY_METHOD_HEX = '7073746b5f706179737461636b';

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
            $legacyPrefix = $this->decodeHex(self::LEGACY_PREFIX_HEX);
            $select = $conn->select()->from($configTable)->where('path LIKE ?', $legacyPrefix . '%');
            foreach ($conn->fetchAll($select) as $row) {
                $newPath = str_replace($legacyPrefix, self::NEW_CONFIG_PREFIX, $row['path']);
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
            $conn->update($orderPayment, ['method' => self::NEW_METHOD], ['method = ?' => $this->decodeHex(self::LEGACY_METHOD_HEX)]);
        }

        $quotePayment = $setup->getTable('quote_payment');
        if ($conn->isTableExists($quotePayment)) {
            $conn->update($quotePayment, ['method' => self::NEW_METHOD], ['method = ?' => $this->decodeHex(self::LEGACY_METHOD_HEX)]);
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

    private function decodeHex(string $value): string
    {
        $decoded = hex2bin($value);
        return $decoded === false ? '' : $decoded;
    }
}
