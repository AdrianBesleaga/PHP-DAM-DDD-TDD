<?php

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use App\Shared\Infrastructure\DoctrineEntityManagerFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$entityManager = DoctrineEntityManagerFactory::create();
$commands = [
    // If you want to add your own custom console commands,
    // you can do so here.
];

ConsoleRunner::run(
    new SingleManagerProvider($entityManager),
    $commands
);
