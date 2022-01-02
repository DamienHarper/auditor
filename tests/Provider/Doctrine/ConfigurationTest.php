<?php

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
final class ConfigurationTest extends TestCase
{
    use ProviderConfigurationTrait;

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
        $ignored = [
            'created_at',
            'updated_at',
        ];

        $configuration = $this->createProviderConfiguration([
            'ignored_columns' => $ignored,
        ]);

        self::assertSame($ignored, $configuration->getIgnoredColumns(), '"ignored_columns" are honored.');
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

    public function testGetExtraFields(): void
    {
        $extraFields = [
            'example_int_field' => [
                'type' => 'integer',
                'options' => [
                    'notnull' => true,
                ],
            ],
            'example_string_field' => [
                'type' => 'string',
                'options' => [
                    'notnull' => false,
                    'length' => 50,
                ],
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'extra_fields' => $extraFields,
        ]);

        self::assertSame($extraFields, $configuration->getExtraFields(), 'AuditConfiguration::getExtraFields() returns configured extra fields list.');
    }

    public function testGetExtraIndices(): void
    {
        $extraIndices = [
            'example_default_index' => null,
            'example_configured_index' => [
                'type' => 'primary',
                'name_prefix' => 'another_prefix',
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'extra_indices' => $extraIndices,
        ]);

        self::assertSame($extraIndices, $configuration->getExtraIndices(), 'AuditConfiguration::getExtraIndices() returns configured extra indices list.');
    }

    public function testPrepareExtraIndices(): void
    {
        $extraIndicesConfig = [
            'example_default_index' => null,
            'example_configured_index' => [
                'type' => 'primary',
                'name_prefix' => 'another_prefix',
            ],
        ];
        $tableName = 'test_table';

        $extraIndicesExpected = [
            'example_default_index' => [
                'type' => 'index',
                'name' => 'example_default_index_'.md5($tableName).'_idx',
            ],
            'example_configured_index' => [
                'type' => 'primary',
                'name' => 'another_prefix_'.md5($tableName).'_idx',
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'extra_indices' => $extraIndicesConfig,
        ]);

        self::assertSame($extraIndicesExpected, $configuration->prepareExtraIndices($tableName), 'AuditConfiguration::prepareExtraIndices() returns transformed index list.');
    }
}
