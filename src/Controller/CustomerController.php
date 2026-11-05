<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\CreateCustomerRequest;
use App\Response\CustomerResponse;
use App\Service\CustomerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customers', format: 'json')]
final class CustomerController extends AbstractController
{
    public function __construct(
        private readonly CustomerService $customers,
    ) {
    }

    #[Route('', name: 'customer_create', methods: ['POST'])]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request);

        return $this->json(
            CustomerResponse::fromCustomer($customer),
            Response::HTTP_CREATED,
            ['Location' => '/api/customers/'.$customer->id->toRfc4122()],
        );
    }
}
