<?php

require 'vendor/autoload.php';

$ref = new ReflectionClass('App\Filament\Widgets\RecentDistributionTable');
echo 'Class loaded OK. Parent: ' . $ref->getParentClass()->getName() . PHP_EOL;

// Check sort property
$sortProp = $ref->getProperty('sort');
$sortProp->setAccessible(true);
echo 'Sort: ' . $sortProp->getDefaultValue() . PHP_EOL;

// Check columnSpan property
$spanProp = $ref->getProperty('columnSpan');
$spanProp->setAccessible(true);
echo 'ColumnSpan: ' . $spanProp->getDefaultValue() . PHP_EOL;

echo 'All checks passed!' . PHP_EOL;
