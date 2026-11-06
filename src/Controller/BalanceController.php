<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\DepositRequest;
use App\Response\DepositResponse;
use App\Service\BalanceService;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/api/customers/{customerId}', requirements: ['customerId' => Requirement::UUID], format: 'json')]
final class BalanceController extends AbstractController
{
    public function __construct(
        private readonly BalanceService $balances,
    ) {
    }

    #[Route('/deposits', name: 'customer_deposit', methods: ['POST'])]
    public function deposit(Uuid $customerId, #[MapRequestPayload(acceptFormat: 'json')] DepositRequest $request): JsonResponse
    {
        /** @var numeric-string $amount */
        $amount = $request->amount;
        $balanceUpdate = $this->balances->deposit($customerId, Money::of($amount));

        return $this->json(
            DepositResponse::fromBalanceUpdate($balanceUpdate),
            Response::HTTP_CREATED,
        );
    }
}
