<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Schema;

use DH\Auditor\Provider\Doctrine\Service\StorageService;
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
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SchemaManager1AEM2SEMTest extends TestCase
{
    use BlogSchemaSetupTrait;

    public function testStorageServicesSetup(): void
    {
        $authorStorageService = $this->provider->getStorageServiceForEntity(Author::class);
        $postStorageService = $this->provider->getStorageServiceForEntity(Post::class);
        $commentStorageService = $this->provider->getStorageServiceForEntity(Comment::class);
        $tagStorageService = $this->provider->getStorageServiceForEntity(Tag::class);

        self::assertSame($authorStorageService, $postStorageService, 'Author and Post use the same storage entity manager.');
        self::assertSame($authorStorageService, $commentStorageService, 'Author and Comment use the same storage entity manager.');
        self::assertSame($authorStorageService, $tagStorageService, 'Author and Tag use the same storage entity manager.');
    }

    /**
     * @depends testStorageServicesSetup
     */
    public function testSchemaSetup(): void
    {
        $storageServices = $this->provider->getStorageServices();
        $expected = [
            'db1' => ['author', 'author_audit', 'comment', 'comment_audit', 'post', 'post_audit', 'tag', 'tag_audit', 'post__tag'],
            'db2' => ['animal', 'animal_audit', 'cat', 'cat_audit', 'dog', 'dog_audit', 'vehicle', 'vehicle_audit'],
        ];
        sort($expected['db1']);
        sort($expected['db2']);

        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($storageServices as $name => $storageService) {
            $schemaManager = $storageService->getEntityManager()->getConnection()->getSchemaManager();
            $tables = array_map(static function ($t) {return $t->getName(); }, $schemaManager->listTables());
            sort($tables);
            self::assertSame($expected[$name], $tables, 'Schema of "'.$name.'" is correct.');
        }
    }

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
}
