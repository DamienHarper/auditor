<?php

declare(strict_types=1);

namespace DH\Auditor\Benchmarks;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration as AuditorConfiguration;
use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorMiddleware;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\User\User;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use PhpBench\Attributes as Bench;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Benchmarks the auditor flush pipeline (TransactionProcessor + persist()).
 *
 * Run:
 *   vendor/bin/phpbench run benchmarks --report=default
 *
 * Store a baseline:
 *   vendor/bin/phpbench run benchmarks --report=default --tag=before
 *
 * Compare against it:
 *   vendor/bin/phpbench run benchmarks --report=default --ref=before
 *
 * Adjust entity count:
 *   BENCH_N=200 vendor/bin/phpbench run benchmarks --report=default
 */
#[Bench\Iterations(5)]
#[Bench\Revs(1)]
#[Bench\Warmup(1)]
class AuditBench
{
    private int $n;
    private EntityManagerInterface $em;
    private DoctrineProvider $provider;

    /** @var Author[] */
    private array $authors = [];

    /** @var Post[] */
    private array $posts = [];

    /** @var Tag[] */
    private array $tags = [];

    // -------------------------------------------------------------------------
    //  benchInsert — N Author inserts audited in a single flush
    // -------------------------------------------------------------------------

    public function setUpInsert(): void
    {
        $this->setUpBase();
    }

    #[Bench\BeforeMethods(['setUpInsert'])]
    #[Bench\AfterMethods(['tearDownBase'])]
    public function benchInsert(): void
    {
        for ($i = 0; $i < $this->n; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@bench.test");
            $this->em->persist($author);
        }
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    //  benchUpdate — N Post updates (3 fields: title, body, created_at) in a single flush
    // -------------------------------------------------------------------------

    public function setUpUpdate(): void
    {
        $this->setUpBase();
        $this->seedPosts();
        $this->em->flush();
    }

    #[Bench\BeforeMethods(['setUpUpdate'])]
    #[Bench\AfterMethods(['tearDownBase'])]
    public function benchUpdate(): void
    {
        $now = new \DateTimeImmutable();
        foreach ($this->posts as $i => $post) {
            $post->setTitle("Updated Post {$i}")
                 ->setBody("Updated body content for post number {$i}")
                 ->setCreatedAt($now);
        }
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    //  benchRemove — N Author removals audited in a single flush
    // -------------------------------------------------------------------------

    public function setUpRemove(): void
    {
        $this->setUpBase();
        $this->seedAuthors();   // Authors, not Posts — keep remove independent of update
        $this->em->flush();
    }

    #[Bench\BeforeMethods(['setUpRemove'])]
    #[Bench\AfterMethods(['tearDownBase'])]
    public function benchRemove(): void
    {
        foreach ($this->authors as $author) {
            $this->em->remove($author);
        }
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    //  benchAssociate — N Post→Tag ManyToMany associations audited in a single flush
    // -------------------------------------------------------------------------

    public function setUpAssociate(): void
    {
        $this->setUpBase();
        $this->seedPostsAndTags();
        $this->em->flush();
    }

    #[Bench\BeforeMethods(['setUpAssociate'])]
    #[Bench\AfterMethods(['tearDownBase'])]
    public function benchAssociate(): void
    {
        $tagCount = \count($this->tags);
        foreach ($this->posts as $i => $post) {
            $post->addTag($this->tags[$i % $tagCount]);
        }
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    //  benchDissociate — N Post→Tag ManyToMany dissociations audited in a single flush
    // -------------------------------------------------------------------------

    public function setUpDissociate(): void
    {
        $this->setUpBase();
        $this->seedPostsAndTags();
        $this->em->flush();

        // Associate first (not measured), then the bench method dissociates
        $tagCount = \count($this->tags);
        foreach ($this->posts as $i => $post) {
            $post->addTag($this->tags[$i % $tagCount]);
        }
        $this->em->flush();
    }

    #[Bench\BeforeMethods(['setUpDissociate'])]
    #[Bench\AfterMethods(['tearDownBase'])]
    public function benchDissociate(): void
    {
        $tagCount = \count($this->tags);
        foreach ($this->posts as $i => $post) {
            $post->removeTag($this->tags[$i % $tagCount]);
        }
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    //  benchMixed — realistic flush: insert + update + remove combined
    // -------------------------------------------------------------------------

    public function setUpMixed(): void
    {
        $this->setUpBase();

        // Pre-seed N/2 authors to update/remove in the benchmark
        $half = max(1, intdiv($this->n, 2));
        for ($i = 0; $i < $half; ++$i) {
            $author = new Author();
            $author->setFullname("Existing {$i}")->setEmail("existing{$i}@bench.test");
            $this->em->persist($author);
            $this->authors[] = $author;
        }
        $this->em->flush();
    }

    #[Bench\BeforeMethods(['setUpMixed'])]
    #[Bench\AfterMethods(['tearDownBase'])]
    public function benchMixed(): void
    {
        $half    = max(1, intdiv($this->n, 2));
        $quarter = max(1, intdiv($this->n, 4));

        // Insert N/2 new authors
        for ($i = 0; $i < $half; ++$i) {
            $author = new Author();
            $author->setFullname("New {$i}")->setEmail("new{$i}@bench.test");
            $this->em->persist($author);
        }

        // Update the first N/4 existing authors (2 fields)
        foreach (\array_slice($this->authors, 0, $quarter) as $i => $author) {
            $author->setFullname("Updated {$i}")->setEmail("updated{$i}@bench.test");
        }

        // Remove the next N/4 existing authors
        foreach (\array_slice($this->authors, $quarter, $quarter) as $author) {
            $this->em->remove($author);
        }

        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    //  Shared setup / teardown helpers
    // -------------------------------------------------------------------------

    private function setUpBase(): void
    {
        $this->n       = max(1, (int) (getenv('BENCH_N') ?: 50));
        $this->authors = [];
        $this->posts   = [];
        $this->tags    = [];

        $this->buildProvider();
        $this->buildSchema();
    }

    public function tearDownBase(): void
    {
        // In-memory SQLite: releasing the EM/connection drops the DB automatically
        $this->authors = [];
        $this->posts   = [];
        $this->tags    = [];
        // PHPBench holds no references; GC will collect the EM/connection
    }

    // -------------------------------------------------------------------------
    //  Data seeders (called from setUp methods, not measured)
    // -------------------------------------------------------------------------

    private function seedAuthors(): void
    {
        for ($i = 0; $i < $this->n; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@bench.test");
            $this->em->persist($author);
            $this->authors[] = $author;
        }
    }

    private function seedPosts(): void
    {
        for ($i = 0; $i < $this->n; ++$i) {
            $post = new Post();
            $post->setTitle("Post {$i}")->setBody("Initial body for post {$i}")->setCreatedAt(new \DateTimeImmutable('2020-01-01'));
            $this->em->persist($post);
            $this->posts[] = $post;
        }
    }

    private function seedPostsAndTags(): void
    {
        $tagCount = max(1, intdiv($this->n, 5));
        for ($i = 0; $i < $tagCount; ++$i) {
            $tag = new Tag();
            $tag->setTitle("Tag {$i}");
            $this->em->persist($tag);
            $this->tags[] = $tag;
        }

        for ($i = 0; $i < $this->n; ++$i) {
            $post = new Post();
            $post->setTitle("Post {$i}")->setBody('Benchmark body')->setCreatedAt(new \DateTimeImmutable());
            $this->em->persist($post);
            $this->posts[] = $post;
        }
    }

    // -------------------------------------------------------------------------
    //  Infrastructure builders
    // -------------------------------------------------------------------------

    private function buildProvider(): void
    {
        $auditorConfig = new AuditorConfiguration(['timezone' => 'UTC', 'enabled' => true]);
        $auditor       = new Auditor($auditorConfig, new EventDispatcher());

        $providerConfig = new Configuration([
            'table_prefix'    => '',
            'table_suffix'    => '_audit',
            'ignored_columns' => [],
            'entities'        => [],
        ]);
        $this->provider = new DoctrineProvider($providerConfig);
        $auditor->registerProvider($this->provider);

        $this->em = $this->buildEntityManager();

        $this->provider->registerStorageService(new StorageService('default', $this->em));
        $this->provider->registerAuditingService(new AuditingService('default', $this->em));

        $this->provider->getAuditor()->getConfiguration()->setUserProvider(
            static fn (): User => new User('1', 'bench.user')
        );
        $this->provider->getAuditor()->getConfiguration()->setSecurityProvider(
            static fn (): array => ['127.0.0.1', 'bench']
        );

        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
            Post::class   => ['enabled' => true],
            Tag::class    => ['enabled' => true],
        ]);
    }

    private function buildEntityManager(): EntityManagerInterface
    {
        $paths = [
            __DIR__.'/../src/Provider/Doctrine/Auditing/Attribute',
            __DIR__.'/../tests/Provider/Doctrine/Fixtures/Entity/Standard/Blog',
        ];

        $ormConfig = ORMSetup::createAttributeMetadataConfiguration($paths, isDevMode: true);
        $ormConfig->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $ormConfig->enableNativeLazyObjects(true);

        $dbalConfig = new DBALConfiguration();
        $dbalConfig->setMiddlewares([new AuditorMiddleware()]);

        $connection = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $dbalConfig
        );

        $em  = new EntityManager($connection, $ormConfig);
        $evm = $em->getEventManager();
        $evm->addEventListener(Events::onFlush, new SoftDeleteableListener());

        return $em;
    }

    private function buildSchema(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $classes    = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($classes);

        $schemaManager = new SchemaManager($this->provider);
        $schemaManager->updateAuditSchema();
    }
}
