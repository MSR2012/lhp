<?php

declare(strict_types=1);

namespace App\DTOs;

final class EventFilterDTO
{
    /**
     * @param string[]|null $allowedStatuses Whitelist of statuses to include. Null means no restriction.
     */
    public function __construct(
        public readonly ?string $status,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly ?string $city,
        public readonly ?array $allowedStatuses = null,
    ) {}
}
