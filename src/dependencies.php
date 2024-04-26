<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\Views\PhpRenderer;
use SleekDB\Store;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        // set template path
        'renderer' => function (ContainerInterface $c) {
            return new PhpRenderer(__DIR__ . '/../templates/');
        },
        // sleek db store
        'store' => function (ContainerInterface $c) {
            return new Store('creds', __DIR__ . '/../db');
        },
        // guzzle client
        'guzzle' => function (ContainerInterface $c) {
            // retry middleware
            $handlerStack = HandlerStack::create();
            $handlerStack->push(GuzzleRetryMiddleware::factory());

            return new Client([
                'retry_on_status' => [429, 500, 502, 503, 504],
                'handler' => $handlerStack,
                'base_uri' => 'https://graphql.anilist.co',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);
        },
        'session' => function () {
            return new \SlimSession\Helper();
        },
    ]);
};