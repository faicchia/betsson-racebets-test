<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\CreateCustomerRequest;
use App\Request\UpdateCustomerRequest;
use App\Response\CustomerResponse;
use App\Service\CustomerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

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

    #[Route('/{id}', name: 'customer_update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function update(Uuid $id, #[MapRequestPayload(acceptFormat: 'json')] UpdateCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->update($id, $request);

        return $this->json(CustomerResponse::fromCustomer($customer));
    }
}
