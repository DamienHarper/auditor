<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Updater;

use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UpdateManager2AEM1SEMTest extends TestCase
{
    use BlogSchemaSetupTrait;

    public function testEntityManagerSetup(): void
    {
        $authorEM = $this->provider->getEntityManagerForEntity(Author::class);
        $postEM = $this->provider->getEntityManagerForEntity(Post::class);
        $commentEM = $this->provider->getEntityManagerForEntity(Comment::class);
        $tagEM = $this->provider->getEntityManagerForEntity(Tag::class);

        self::assertSame($authorEM, $postEM, 'Author and Post use the same storage entity manager.');
        self::assertSame($authorEM, $commentEM, 'Author and Comment use the same storage entity manager.');
        self::assertSame($authorEM, $tagEM, 'Author and Tag use the same storage entity manager.');
    }

    /**
     * @depends testEntityManagerSetup
     */
    public function testSchemaSetup(): void
    {
        $entityManagers = $this->provider->getStorageEntityManagers();

        $expected = [
            'sem1' => ['author', 'author_audit', 'comment', 'comment_audit', 'post', 'post_audit', 'tag', 'tag_audit', 'post__tag', 'dummy_entity'],
        ];
        sort($expected['sem1']);

        foreach ($entityManagers as $name => $entityManager) {
            $schemaManager = $entityManager->getConnection()->getSchemaManager();
            $tables = array_map(static function ($t) {return $t->getName(); }, $schemaManager->listTables());
            sort($tables);
            self::assertSame($expected[$name], $tables, 'Schema of "'.$name.'" is correct.');
        }
    }

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProviderWith2AEM1SEM();
    }
}
