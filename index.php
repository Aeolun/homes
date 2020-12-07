#!/usr/bin/env php
<?php
// application.php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();

$retrieveCommand = new \Bart\Homes\Command\RetrieveCommand();
$application->add($retrieveCommand);

$application->run();