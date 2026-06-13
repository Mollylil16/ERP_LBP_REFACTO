#!/usr/bin/env php
<?php
// Script CLI pour lancer les migrations via le bootstrap
chdir(__DIR__ . '/../');
require __DIR__ . '/../bootstrap/app.php';

echo "Migrations executed (voir logs ou table schema_migrations).\n";
