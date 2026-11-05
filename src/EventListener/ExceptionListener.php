<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\EmailAlreadyTaken;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * Turns every exception into a consistent JSON response with status, message, and errors fields.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $event->setResponse($this->toResponse($event->getThrowable()));
    }

    private function toResponse(Throwable $exception): JsonResponse
    {
        // #[MapRequestPayload] wraps both validation and type errors in a 422 HttpException
        if ($exception->getPrevious() instanceof ValidationFailedException) {
            return self::jsonResponse(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The request contains invalid fields.',
                self::errorsByField($exception->getPrevious())
            );
        }

        return match (true) {
            $exception instanceof EmailAlreadyTaken => self::jsonResponse(
                Response::HTTP_CONFLICT,
                $exception->getMessage(),
                ['email' => 'This email is already taken.'],
            ),
            $exception instanceof HttpExceptionInterface => self::jsonResponse(
                $exception->getStatusCode(),
                $exception->getMessage(),
                headers: $exception->getHeaders(),
            ),
            // Never expose the message of an unexpected error; Symfony has already logged it
            default => self::jsonResponse(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'An unexpected error occurred.'
            ),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function errorsByField(ValidationFailedException $exception): array
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            // Request constraints stop at a field's first failure; keep the first message if one still repeats
            $errors[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        return $errors;
    }

    /**
     * @param array<string, string> $errors
     * @param array<mixed>          $headers
     */
    private static function jsonResponse(
        int $status,
        string $message,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        // Ensure an empty errors array encodes as an empty JSON object ({}) instead of an array ([])
        $formattedErrors = empty($errors) ? (object) [] : $errors;

        return new JsonResponse(
            json_encode([
                'status' => $status,
                'message' => '' !== $message ? $message : (Response::$statusTexts[$status] ?? 'Error'),
                'errors' => $formattedErrors,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json'] + $headers,
            json: true,
        );
    }
}
