<?php

namespace DH\Auditor\Provider;

use DH\Auditor\Auditor;

abstract class AbstractProvider implements ProviderInterface
{
    /**
     * @var Auditor
     */
    protected $auditor;

    public function setAuditor(Auditor $auditor): ProviderInterface
    {
        $this->auditor = $auditor;

        return $this;
    }

    public function getAuditor(): Auditor
    {
        return $this->auditor;
    }
}
