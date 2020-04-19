<?php

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Provider\Doctrine\Audit\Annotation\AnnotationLoader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\DoctrineProviderTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DoctrineProviderTest extends TestCase
{
    use DoctrineProviderTrait;

    public function testIsAudited(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');
        self::assertFalse($provider->isAudited(Comment::class), 'Entity "'.Comment::class.'" is not audited.');
    }

    public function testIsAuditable(): void
    {
        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
        self::assertTrue($provider->isAuditable(Post::class), 'Entity "'.Post::class.'" is auditable.');
        self::assertFalse($provider->isAudited(Comment::class), 'Entity "'.Comment::class.'" is not audited.');
        self::assertFalse($provider->isAuditable(Comment::class), 'Entity "'.Comment::class.'" is not auditable.');
    }

    /**
     * @depends testIsAudited
     */
    public function testIsAuditedHonorsEnabledFlag(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    /**
     * @depends testIsAudited
     */
    public function testIsAuditedWhenAuditIsEnabled(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);
        $provider->getAuditor()->getConfiguration()->enable();

        self::assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);
        $provider->getAuditor()->getConfiguration()->enable();

        self::assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    /**
     * @depends testIsAudited
     */
    public function testIsAuditedWhenAuditIsDisabled(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

        $provider->getAuditor()->getConfiguration()->disable();

        self::assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    /**
     * @depends testIsAudited
     */
    public function testIsAuditedFieldAuditsAnyFieldByDefault(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAuditedField(Post::class, 'id'), 'Every field is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'title'), 'Every field is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'created_at'), 'Every field is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'updated_at'), 'Every field is audited.');
    }

    /**
     * @depends testIsAuditedFieldAuditsAnyFieldByDefault
     */
    public function testIsAuditedFieldHonorsLocallyIgnoredColumns(): void
    {
        $entities = [
            Post::class => [
                'ignored_columns' => [
                    'created_at',
                    'updated_at',
                ],
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAuditedField(Post::class, 'id'), 'Field "'.Post::class.'::$id" is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'title'), 'Field "'.Post::class.'::$title" is audited.');
        self::assertFalse($provider->isAuditedField(Post::class, 'created_at'), 'Field "'.Post::class.'::$created_at" is not audited.');
        self::assertFalse($provider->isAuditedField(Post::class, 'updated_at'), 'Field "'.Post::class.'::$updated_at" is not audited.');
    }

    /**
     * @depends testIsAuditedFieldHonorsLocallyIgnoredColumns
     */
    public function testIsAuditedFieldHonorsGloballyIgnoredColumns(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'ignored_columns' => [
                'created_at',
                'updated_at',
            ],
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAuditedField(Post::class, 'id'), 'Field "'.Post::class.'::$id" is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'title'), 'Field "'.Post::class.'::$title" is audited.');
        self::assertFalse($provider->isAuditedField(Post::class, 'created_at'), 'Field "'.Post::class.'::$created_at" is not audited.');
        self::assertFalse($provider->isAuditedField(Post::class, 'updated_at'), 'Field "'.Post::class.'::$updated_at" is not audited.');
    }

    /**
     * @depends testIsAuditedFieldHonorsLocallyIgnoredColumns
     */
    public function testIsAuditedFieldReturnsFalseIfEntityIsNotAudited(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'ignored_columns' => [
                'created_at',
                'updated_at',
            ],
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertFalse($provider->isAuditedField(Comment::class, 'id'), 'Field "'.Comment::class.'::$id" is audited but "'.Comment::class.'" entity is not.');
    }

    /**
     * @depends testIsAuditedHonorsEnabledFlag
     */
    public function testEnableAuditFor(): void
    {
        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertFalse($provider->isAudited(Post::class), 'entity "'.Post::class.'" is not audited.');

        $configuration->enableAuditFor(Post::class);

        self::assertTrue($provider->isAudited(Post::class), 'entity "'.Post::class.'" is audited.');
    }

    /**
     * @depends testIsAuditedHonorsEnabledFlag
     */
    public function testDisableAuditFor(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAudited(Post::class), 'entity "'.Post::class.'" is audited.');

        $configuration->disableAuditFor(Post::class);

        self::assertFalse($provider->isAudited(Post::class), 'entity "'.Post::class.'" is not audited.');
    }

    public function testSetEntities(): void
    {
        $configuration = $this->createProviderConfiguration([
            'entities' => [Tag::class => null],
        ]);
        $entities1 = $configuration->getEntities();

        $entities = [
            Post::class => null,
            Comment::class => null,
        ];

        $configuration->setEntities($entities);
        $entities2 = $configuration->getEntities();

        self::assertNotSame($entities2, $entities1, 'Configuration::setEntities() replaces previously configured entities.');
    }

    public function testGetAnnotationReader(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertInstanceOf(AnnotationLoader::class, $provider->getAnnotationLoader(), 'AnnotationLoader is set.');
    }

    public function testPersist(): void
    {
        self::markTestIncomplete('Not yet implemented.');
    }
}
