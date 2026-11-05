<?php

declare(strict_types=1);

it('answers HTTP requests through the kernel', function () {
    $client = self::createClient();

    $client->request('GET', '/does-not-exist');

    expect($client->getResponse()->getStatusCode())->toBe(404);
});
