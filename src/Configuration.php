<?php

namespace DH\Auditor;

use Symfony\Component\OptionsResolver\OptionsResolver;

class Configuration
{
    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var string
     */
    private $timezone;

    public function __construct(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $config = $resolver->resolve($options);

        $this->enabled = $config['enabled'];
        $this->timezone = $config['timezone'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // https://symfony.com/doc/current/components/options_resolver.html
        $resolver
            ->setDefaults([
                'enabled' => true,
                'timezone' => 'UTC',
            ])
            ->setAllowedTypes('enabled', 'bool')
            ->setAllowedTypes('timezone', 'string')
        ;
    }

    /**
     * enabled auditing.
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * disable auditing.
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Is auditing enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the value of timezone.
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }
}
