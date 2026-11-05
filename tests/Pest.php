<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// boots the Symfony kernel to reach real services and the test DB
pest()->extend(KernelTestCase::class)->in('Integration');
// boots the kernel and sends HTTP requests through it
pest()->extend(WebTestCase::class)->in('Api');

function postCustomer(KernelBrowser $client, array|string $payload): array
{
    $client->request(
        'POST',
        '/api/customers',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: is_string($payload) ? $payload : json_encode($payload),
    );

    return json_decode($client->getResponse()->getContent(), true) ?? [];
}

function validCustomer(array $overrides = []): array
{
    return [
        'email' => 'jane.doe@example.com',
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'gender' => 'female',
        'country' => 'DE',
        ...$overrides,
    ];
}
