<?php

declare(strict_types=1);

namespace DH\Auditor;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see Tests\ConfigurationTest
 */
final class Configuration
{
    public private(set) bool $enabled;

    public readonly string $timezone;

    /**
     * @var callable|null
     */
    private mixed $userProvider = null;

    /**
     * @var callable|null
     */
    private mixed $roleChecker = null;

    /**
     * @var callable|null
     */
    private mixed $securityProvider = null;

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

    /**
     * Enable auditing.
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable auditing.
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    public function setUserProvider(callable $userProvider): self
    {
        $this->userProvider = $userProvider;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getUserProvider(): mixed
    {
        return $this->userProvider;
    }

    public function setSecurityProvider(callable $securityProvider): self
    {
        $this->securityProvider = $securityProvider;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getSecurityProvider(): mixed
    {
        return $this->securityProvider;
    }

    public function setRoleChecker(callable $roleChecker): self
    {
        $this->roleChecker = $roleChecker;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getRoleChecker(): mixed
    {
        return $this->roleChecker;
    }

    private function configureOptions(OptionsResolver $resolver): void
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
}
