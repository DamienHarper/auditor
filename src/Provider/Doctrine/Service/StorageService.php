<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Service;

use DH\Auditor\Provider\Service\StorageServiceInterface;

/**
 * @deprecated since auditor 4.x, to be removed in v5.0. Use damienharper/auditor-doctrine-provider instead.
 */
final class StorageService extends DoctrineService implements StorageServiceInterface {}
