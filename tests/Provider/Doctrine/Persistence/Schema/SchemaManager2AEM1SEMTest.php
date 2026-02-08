<?php

declare(strict_types=1);

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
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class SchemaManager2AEM1SEMTest extends TestCase
{
    use BlogSchemaSetupTrait;

    public function testStorageServicesSetup(): void
    {
        $authorStorageService = $this->provider->getStorageServiceForEntity(Author::class);
        $postStorageService = $this->provider->getStorageServiceForEntity(Post::class);
        $commentStorageService = $this->provider->getStorageServiceForEntity(Comment::class);
        $tagStorageService = $this->provider->getStorageServiceForEntity(Tag::class);

        $this->assertSame($authorStorageService, $postStorageService, 'Author and Post use the same storage entity manager.');
        $this->assertSame($authorStorageService, $commentStorageService, 'Author and Comment use the same storage entity manager.');
        $this->assertSame($authorStorageService, $tagStorageService, 'Author and Tag use the same storage entity manager.');

        $animalStorageService = $this->provider->getStorageServiceForEntity(Animal::class);
        $catStorageService = $this->provider->getStorageServiceForEntity(Cat::class);
        $dogStorageService = $this->provider->getStorageServiceForEntity(Dog::class);
        $vehicleStorageService = $this->provider->getStorageServiceForEntity(Vehicle::class);

        $this->assertSame($authorStorageService, $animalStorageService, 'Author and Animal use the same storage entity manager.');
        $this->assertSame($animalStorageService, $catStorageService, 'Animal and Cat use the same storage entity manager.');
        $this->assertSame($animalStorageService, $dogStorageService, 'Animal and Dog use the same storage entity manager.');
        $this->assertSame($animalStorageService, $vehicleStorageService, 'Animal and Vehicle use the same storage entity manager.');
    }

    #[Depends('testStorageServicesSetup')]
    public function testSchemaSetup(): void
    {
        $storageServices = $this->provider->getStorageServices();
        $configuration = $this->provider->getConfiguration();

        $expected = [
            'sem1' => ['post__tag'],
        ];
        $entities = $configuration->getEntities();
        foreach ($entities as $entityOptions) {
            if (!\in_array($entityOptions['computed_table_name'], $expected['sem1'], true)) {
                $expected['sem1'][] = $entityOptions['computed_table_name'];
            }

            if (!\in_array($entityOptions['computed_audit_table_name'], $expected['sem1'], true)) {
                $expected['sem1'][] = $entityOptions['computed_audit_table_name'];
            }
        }

        sort($expected['sem1']);

        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($storageServices as $name => $storageService) {
            $connection = $storageService->getEntityManager()->getConnection();
            $schemaManager = $connection->createSchemaManager();
            $tables = array_map(static fn ($t): string => $t->getName(), $schemaManager->listTables());
            sort($tables);
            $this->assertSame($expected[$name], $tables, 'Schema of "'.$name.'" is correct.');
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
        $this->provider = $this->createDoctrineProviderWith2AEM1SEM();
    }
}
