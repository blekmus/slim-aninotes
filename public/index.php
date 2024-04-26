<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

// load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__. '/../');
$dotenv->load();

// instantiate PHP-DI container
$containerBuilder = new ContainerBuilder();

// set up dependencies
$dependencies = require __DIR__ . '/../src/dependencies.php';
$dependencies($containerBuilder);

// build the container
$container = $containerBuilder->build();

// create the app
AppFactory::setContainer($container);
$app = AppFactory::create();

// add middleware
$app->add(
    new \Slim\Middleware\Session([
        'name' => 'aniNotes',
        'autorefresh' => true,
        'lifetime' => '1 hour',
        'secure' => 'true',
    ])
);

// register routes
$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

// run app
$app->run();