<?php

declare(strict_types=1);

beforeEach(function () {
    $this->client = self::createClient();
    resetDatabase(self::getContainer()->get(App\Database\Connection::class));
    $this->customer = postCustomer($this->client, validCustomer());
});

function getCustomer(Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $id): array
{
    $client->request('GET', '/api/customers/'.$id);

    return json_decode($client->getResponse()->getContent(), true) ?? [];
}

it('returns the customer', function () {
    $body = getCustomer($this->client, $this->customer['id']);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($this->client->getResponse()->headers->get('Content-Type'))->toBe('application/json')
        ->and($body)->toBe($this->customer);
});

it('returns 404 for an unknown customer', function () {
    $body = getCustomer($this->client, '019a5a00-0000-7000-8000-000000000001');

    expect($this->client->getResponse()->getStatusCode())->toBe(404)
        ->and($body)->toBe([
            'status' => 404,
            'message' => "Customer '019a5a00-0000-7000-8000-000000000001' not found.",
            'errors' => [],
        ]);
});

it('returns 404 for an id that is not a UUID', function () {
    getCustomer($this->client, 'not-a-uuid');

    expect($this->client->getResponse()->getStatusCode())->toBe(404);
});
