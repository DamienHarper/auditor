<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Security;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Annotation\AuditableButUnauditedEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Annotation\AuditedEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ProviderConfigurationTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\Small]
final class ConfigurationTest extends TestCase
{
    use ProviderConfigurationTrait;

    /**
     * @var string[]
     */
    private const IGNORED = [
        'created_at',
        'updated_at',
    ];

    public function testGetProvider(): void
    {
        $configuration = $this->createProviderConfiguration();

        self::assertNull($configuration->getProvider(), 'provider is null by default.');
    }

    public function testDefaultTablePrefix(): void
    {
        $configuration = $this->createProviderConfiguration();

        self::assertSame('', $configuration->getTablePrefix(), '"table_prefix" is empty by default.');
    }

    public function testDefaultTableSuffix(): void
    {
        $configuration = $this->createProviderConfiguration();

        self::assertSame('_audit', $configuration->getTableSuffix(), '"table_suffix" is "_audit" by default.');
    }

    public function testCustomTablePrefix(): void
    {
        $configuration = $this->createProviderConfiguration([
            'table_prefix' => 'audit_',
        ]);

        self::assertSame('audit_', $configuration->getTablePrefix(), 'Custom "table_prefix" is "audit_".');
    }

    public function testCustomTableSuffix(): void
    {
        $configuration = $this->createProviderConfiguration([
            'table_suffix' => '_audit_log',
        ]);

        self::assertSame('_audit_log', $configuration->getTableSuffix(), 'Custom "table_suffix" is "_audit_log".');
    }

    public function testIsViewerEnabledByDefault(): void
    {
        $configuration = $this->createProviderConfiguration();

        self::assertTrue($configuration->isViewerEnabled(), 'Viewer is enabled by default.');
    }

    public function testDisableViewer(): void
    {
        $configuration = $this->createProviderConfiguration();
        $configuration->disableViewer();

        self::assertFalse($configuration->isViewerEnabled(), 'Viewer is disabled.');
    }

    public function testEnableViewer(): void
    {
        $configuration = $this->createProviderConfiguration();
        $configuration->disableViewer();
        $configuration->enableViewer();

        self::assertTrue($configuration->isViewerEnabled(), 'Viewer is enabled.');
    }

    public function testGloballyIgnoredColumns(): void
    {
        $configuration = $this->createProviderConfiguration([
            'ignored_columns' => self::IGNORED,
        ]);

        self::assertSame(self::IGNORED, $configuration->getIgnoredColumns(), '"ignored_columns" are honored.');
    }

    public function testGetEntities(): void
    {
        $entities = [
            Post::class => null,
            Comment::class => null,
            AuditedEntity::class => [
                'ignored_columns' => ['ignoredField'],
                'enabled' => true,
                'roles' => null,
            ],
            AuditableButUnauditedEntity::class => [
                'ignored_columns' => ['ignoredField'],
                'enabled' => false,
                'roles' => [
                    Security::VIEW_SCOPE => ['ROLE1', 'ROLE2'],
                ],
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);

        self::assertSame($entities, $configuration->getEntities(), 'AuditConfiguration::getEntities() returns configured entities list.');
    }
}
