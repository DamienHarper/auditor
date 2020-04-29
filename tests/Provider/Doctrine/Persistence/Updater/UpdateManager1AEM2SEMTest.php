<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Updater;

use DH\Auditor\Provider\Doctrine\Persistence\Updater\UpdateManager;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Bike;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Car;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Vehicle;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UpdateManager1AEM2SEMTest extends TestCase
{
    use BlogSchemaSetupTrait;

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
            Post::class => ['enabled' => true],
            Comment::class => ['enabled' => true],
            Tag::class => ['enabled' => true],

            Animal::class => ['enabled' => true],
            Cat::class => ['enabled' => true],
            Dog::class => ['enabled' => true],
            Vehicle::class => ['enabled' => true],
            Bike::class => ['enabled' => true],
            Car::class => ['enabled' => true],
        ]);
    }

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProviderWith1AEM2SEM();
    }

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
            'sem1' => ['author', 'author_audit', 'comment', 'comment_audit', 'post', 'post_audit', 'tag', 'tag_audit', 'post__tag'],
            'sem2' => ['animal', 'animal_audit', 'cat', 'cat_audit', 'dog', 'dog_audit', 'vehicle', 'vehicle_audit'],
        ];
        sort($expected['sem1']);
        sort($expected['sem2']);

        foreach ($entityManagers as $name => $entityManager) {
            $schemaManager = $entityManager->getConnection()->getSchemaManager();
            $tables = array_map(static function($t) {return $t->getName();}, $schemaManager->listTables());
            sort($tables);
            self::assertSame($expected[$name], $tables, 'Schema of "'.$name.'" is correct.');
        }
    }
}
