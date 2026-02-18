<?php

/**
 * Blackfire profiling script for the auditor flush pipeline.
 *
 * Usage:
 *   blackfire run php benchmarks/profile.php
 *
 * Adjust entity count:
 *   BENCH_N=200 blackfire run php benchmarks/profile.php
 *
 * Without Blackfire (dry run):
 *   php benchmarks/profile.php
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

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
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Symfony\Component\EventDispatcher\EventDispatcher;

$n = max(1, (int) (getenv('BENCH_N') ?: 100));

echo "auditor flush pipeline — Blackfire profile\n";
echo "N = {$n} entities per operation\n\n";

// -------------------------------------------------------------------------
//  Bootstrap
// -------------------------------------------------------------------------

$paths = [
    __DIR__.'/../src/Provider/Doctrine/Auditing/Attribute',
    __DIR__.'/../tests/Provider/Doctrine/Fixtures/Entity/Standard/Blog',
];

$ormConfig = ORMSetup::createAttributeMetadataConfiguration($paths, isDevMode: false);
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

$auditorConfig = new AuditorConfiguration(['timezone' => 'UTC', 'enabled' => true]);
$auditor       = new Auditor($auditorConfig, new EventDispatcher());

$providerConfig = new Configuration([
    'table_prefix'    => '',
    'table_suffix'    => '_audit',
    'ignored_columns' => [],
    'entities'        => [],
]);
$provider = new DoctrineProvider($providerConfig);
$auditor->registerProvider($provider);
$provider->registerStorageService(new StorageService('default', $em));
$provider->registerAuditingService(new AuditingService('default', $em));

$provider->getAuditor()->getConfiguration()->setUserProvider(
    static fn (): User => new User('1', 'bench.user')
);
$provider->getAuditor()->getConfiguration()->setSecurityProvider(
    static fn (): array => ['127.0.0.1', 'bench']
);

$provider->getConfiguration()->setEntities([
    Author::class => ['enabled' => true],
    Post::class   => ['enabled' => true],
    Tag::class    => ['enabled' => true],
]);

// Build schemas
$schemaTool = new SchemaTool($em);
$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

$schemaManager = new SchemaManager($provider);
$schemaManager->updateAuditSchema();

// -------------------------------------------------------------------------
//  Phase 1: INSERT — N Authors
// -------------------------------------------------------------------------

echo "Phase 1: INSERT {$n} Authors ... ";
$authors = [];
for ($i = 0; $i < $n; ++$i) {
    $author = new Author();
    $author->setFullname("Author {$i}")->setEmail("author{$i}@bench.test");
    $em->persist($author);
    $authors[] = $author;
}
$em->flush();
echo "done\n";

// -------------------------------------------------------------------------
//  Phase 2: UPDATE — N Authors
// -------------------------------------------------------------------------

echo "Phase 2: UPDATE {$n} Authors ... ";
foreach ($authors as $i => $author) {
    $author->setFullname("Author {$i} Updated");
}
$em->flush();
echo "done\n";

// -------------------------------------------------------------------------
//  Phase 3: ASSOCIATE — N Post→Tag (ManyToMany)
// -------------------------------------------------------------------------

echo "Phase 3: ASSOCIATE {$n} Post→Tag ... ";
$tagCount = max(1, intdiv($n, 5));
$tags     = [];
for ($i = 0; $i < $tagCount; ++$i) {
    $tag = new Tag();
    $tag->setTitle("Tag {$i}");
    $em->persist($tag);
    $tags[] = $tag;
}

$posts = [];
for ($i = 0; $i < $n; ++$i) {
    $post = new Post();
    $post->setTitle("Post {$i}")->setBody('Benchmark body')->setCreatedAt(new \DateTimeImmutable());
    $em->persist($post);
    $posts[] = $post;
}
$em->flush(); // inserts (not the measured path for this phase)

foreach ($posts as $i => $post) {
    $post->addTag($tags[$i % $tagCount]);
}
$em->flush();
echo "done\n";

// -------------------------------------------------------------------------
//  Phase 4: DISSOCIATE — N Post→Tag (ManyToMany)
// -------------------------------------------------------------------------

echo "Phase 4: DISSOCIATE {$n} Post→Tag ... ";
foreach ($posts as $i => $post) {
    $post->removeTag($tags[$i % $tagCount]);
}
$em->flush();
echo "done\n";

// -------------------------------------------------------------------------
//  Phase 5: REMOVE — N/4 Authors
// -------------------------------------------------------------------------

$removeCount = max(1, intdiv($n, 4));
echo "Phase 5: REMOVE {$removeCount} Authors ... ";
foreach (\array_slice($authors, 0, $removeCount) as $author) {
    $em->remove($author);
}
$em->flush();
echo "done\n";

echo "\nProfile complete.\n";
