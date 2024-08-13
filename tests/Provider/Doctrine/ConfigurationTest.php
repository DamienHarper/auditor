<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Security;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Annotation\AuditableButUnauditedEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Annotation\AuditedEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ProviderConfigurationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversClass(Configuration::class)]
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

        $this->assertNull($configuration->getProvider(), 'provider is null by default.');
    }

    public function testDefaultTablePrefix(): void
    {
        $configuration = $this->createProviderConfiguration();

        $this->assertSame('', $configuration->getTablePrefix(), '"table_prefix" is empty by default.');
    }

    public function testDefaultTableSuffix(): void
    {
        $configuration = $this->createProviderConfiguration();

        $this->assertSame('_audit', $configuration->getTableSuffix(), '"table_suffix" is "_audit" by default.');
    }

    public function testCustomTablePrefix(): void
    {
        $configuration = $this->createProviderConfiguration([
            'table_prefix' => 'audit_',
        ]);

        $this->assertSame('audit_', $configuration->getTablePrefix(), 'Custom "table_prefix" is "audit_".');
    }

    public function testCustomTableSuffix(): void
    {
        $configuration = $this->createProviderConfiguration([
            'table_suffix' => '_audit_log',
        ]);

        $this->assertSame('_audit_log', $configuration->getTableSuffix(), 'Custom "table_suffix" is "_audit_log".');
    }

    public function testIsViewerEnabledByDefault(): void
    {
        $configuration = $this->createProviderConfiguration();

        $this->assertTrue($configuration->isViewerEnabled(), 'Viewer is enabled by default.');
    }

    public function testDisableViewer(): void
    {
        $configuration = $this->createProviderConfiguration();
        $configuration->disableViewer();

        $this->assertFalse($configuration->isViewerEnabled(), 'Viewer is disabled.');
    }

    public function testEnableViewer(): void
    {
        $configuration = $this->createProviderConfiguration();
        $configuration->disableViewer();
        $configuration->enableViewer();

        $this->assertTrue($configuration->isViewerEnabled(), 'Viewer is enabled.');
    }

    public function testGloballyIgnoredColumns(): void
    {
        $configuration = $this->createProviderConfiguration([
            'ignored_columns' => self::IGNORED,
        ]);

        $this->assertSame(self::IGNORED, $configuration->getIgnoredColumns(), '"ignored_columns" are honored.');
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

        $this->assertSame($entities, $configuration->getEntities(), 'AuditConfiguration::getEntities() returns configured entities list.');
    }
}
