<?php

declare(strict_types=1);

use App\EventListener\ExceptionListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

function handle(Throwable $exception): Response
{
    $event = new ExceptionEvent(
        new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        },
        new Request(),
        HttpKernelInterface::MAIN_REQUEST,
        $exception,
    );

    new ExceptionListener()($event);

    return $event->getResponse();
}

it('hides the message of an unexpected error', function () {
    $response = handle(new RuntimeException('SQLSTATE[HY000] secret connection details'));

    expect($response->getStatusCode())->toBe(500)
        ->and(json_decode($response->getContent(), true))->toBe([
            'status' => 500,
            'message' => 'An unexpected error occurred.',
            'errors' => [],
        ]);
});

it('encodes empty errors as a JSON object', function () {
    $response = handle(new RuntimeException());

    expect($response->getContent())->toContain('"errors":{}');
});

it('keeps the headers of an HTTP exception', function () {
    $response = handle(new MethodNotAllowedHttpException(['GET', 'PATCH'], 'Method not allowed.'));

    expect($response->getStatusCode())->toBe(405)
        ->and($response->headers->get('Allow'))->toBe('GET, PATCH')
        ->and($response->headers->get('Content-Type'))->toBe('application/json');
});
