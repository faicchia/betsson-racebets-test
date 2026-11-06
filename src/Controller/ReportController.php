<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\ReportRequest;
use App\Response\ReportResponse;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports', format: 'json')]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly ReportService $reports,
    ) {
    }

    #[Route('/daily-transaction-totals', name: 'report_daily_transaction_totals', methods: ['GET'])]
    public function dailyTotalsByCountry(
        // without a query string Symfony skips mapping and passes the default
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ReportRequest $request = new ReportRequest(),
    ): JsonResponse {
        $report = $this->reports->dailyTotalsByCountry($request->fromDate(), $request->toDate());

        return $this->json(ReportResponse::fromReport($report));
    }
}
