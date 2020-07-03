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

    /**
     * @var callable
     */
    private $userProvider;

    /**
     * @var callable
     */
    private $roleChecker;

    /**
     * @var callable
     */
    private $securityProvider;

    public function __construct(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $config = $resolver->resolve($options);

        $this->enabled = $config['enabled'];
        $this->timezone = $config['timezone'];
        $this->userProvider = $config['user_provider'];
        $this->securityProvider = $config['security_provider'];
        $this->roleChecker = $config['role_checker'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // https://symfony.com/doc/current/components/options_resolver.html
        $resolver
            ->setDefaults([
                'enabled' => true,
                'timezone' => 'UTC',
                'role_checker' => null,
                'user_provider' => null,
                'security_provider' => null,
            ])
            ->setAllowedTypes('enabled', 'bool')
            ->setAllowedTypes('timezone', 'string')
            ->setAllowedTypes('role_checker', ['null', 'string', 'callable'])
            ->setAllowedTypes('user_provider', ['null', 'string', 'callable'])
            ->setAllowedTypes('security_provider', ['null', 'string', 'callable'])
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

    public function setUserProvider(callable $userProvider): self
    {
        $this->userProvider = $userProvider;

        return $this;
    }

    public function getUserProvider(): ?callable
    {
        return $this->userProvider;
    }

    public function setSecurityProvider(callable $securityProvider): self
    {
        $this->securityProvider = $securityProvider;

        return $this;
    }

    public function getSecurityProvider(): ?callable
    {
        return $this->securityProvider;
    }

    public function setRoleChecker(callable $roleChecker): self
    {
        $this->roleChecker = $roleChecker;

        return $this;
    }

    public function getRoleChecker(): ?callable
    {
        return $this->roleChecker;
    }
}
