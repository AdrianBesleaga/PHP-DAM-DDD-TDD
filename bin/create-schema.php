<?php

declare(strict_types=1);

/**
 * Create the SQLite database schema from Doctrine entity mappings.
 *
 * Usage: php bin/create-schema.php
 */

require __DIR__ . '/../vendor/autoload.php';

$createEntityManager = require __DIR__ . '/../config/doctrine.php';
$em = $createEntityManager();

$schemaTool = new Doctrine\ORM\Tools\SchemaTool($em);
$metadata = $em->getMetadataFactory()->getAllMetadata();

if (empty($metadata)) {
    echo "No entity metadata found. Make sure entities have Doctrine attributes.\n";
    exit(1);
}

// Drop and recreate all tables
$schemaTool->dropSchema($metadata);
$schemaTool->createSchema($metadata);

echo "✅ Database schema created successfully at var/database.sqlite\n";
echo "Tables created:\n";
foreach ($metadata as $meta) {
    echo "  - {$meta->getTableName()}\n";
}
