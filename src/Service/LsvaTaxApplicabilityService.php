<?php declare(strict_types=1);

namespace Topdata\TopdataSurchargeSW6\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class LsvaTaxApplicabilityService
{
    public const DEFAULT_CUSTOM_FIELD = 'topdata_ipim_lsva';
    public const CONFIG_KEY = 'TopdataSurchargeSW6.config.lsvaCustomerField';

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function isLsvaApplicable(SalesChannelContext $context): bool
    {
        $customer = $context->getCustomer();

        if ($customer === null) {
            return false;
        }

        $fieldName = $this->getFieldName($context);
        $customFields = $customer->getCustomFields() ?? [];
        $value = $this->normalize($customFields[$fieldName] ?? null);

        if ($value !== null) {
            return $value;
        }

        if (!$customer->getGuest()) {
            return false;
        }

        return $this->findPreviousGuestValue($customer, $fieldName) ?? false;
    }

    private function getFieldName(SalesChannelContext $context): string
    {
        $fieldName = trim($this->systemConfigService->getString(
            self::CONFIG_KEY,
            $context->getSalesChannelId()
        ));

        if (preg_match('/^[a-zA-Z0-9_]+$/', $fieldName) !== 1) {
            return self::DEFAULT_CUSTOM_FIELD;
        }

        return $fieldName;
    }

    private function findPreviousGuestValue(CustomerEntity $customer, string $fieldName): ?bool
    {
        $jsonPath = '$.' . $fieldName;

        $sql = sprintf(
            "SELECT custom_fields
             FROM customer
             WHERE email = :email
               AND guest = 1
               AND LOWER(HEX(id)) != :currentId
               AND JSON_UNQUOTE(JSON_EXTRACT(custom_fields, '%s')) IS NOT NULL
               AND JSON_UNQUOTE(JSON_EXTRACT(custom_fields, '%s')) NOT IN ('', 'null')
             ORDER BY created_at DESC
             LIMIT 25",
            $jsonPath,
            $jsonPath
        );

        $rows = $this->connection->fetchAllAssociative($sql, [
            'email' => $customer->getEmail(),
            'currentId' => $customer->getId(),
        ]);

        foreach ($rows as $row) {
            $customFields = json_decode((string) ($row['custom_fields'] ?? '[]'), true);

            if (!is_array($customFields)) {
                continue;
            }

            $value = $this->normalize($customFields[$fieldName] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalize(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        if (is_float($value)) {
            return match ($value) {
                1.0 => true,
                0.0 => false,
                default => null,
            };
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['yes', 'ja', 'true', '1'], true)) {
            return true;
        }

        if (in_array($normalized, ['no', 'nein', 'false', '0'], true)) {
            return false;
        }

        return null;
    }
}
