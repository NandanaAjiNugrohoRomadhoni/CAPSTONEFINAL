<?php

namespace App\Libraries;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class JsonApiExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        try {
            $response->setStatusCode($statusCode);
        } catch (HTTPException) {
            $statusCode = 500;
            $response->setStatusCode($statusCode);
        }

        $error = [
            'message' => $this->getErrorMessage($exception, $statusCode),
            'status'  => $statusCode,
        ];

        if (ENVIRONMENT !== 'production') {
            $error['type'] = get_class($exception);
            $error['file'] = $exception->getFile();
            $error['line'] = $exception->getLine();
            
            if (ENVIRONMENT === 'development') {
                $error['trace'] = $this->getCleanedTrace($exception);
            }
        }

        $this->clearOutputBuffers();

        $response->setContentType('application/json')
                 ->setBody(json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                 ->send();

        if (ENVIRONMENT !== 'testing') {
            exit($exitCode);
        }
    }

    protected function getErrorMessage(Throwable $exception, int $statusCode): string
    {
        if (ENVIRONMENT === 'production') {
            return match ($statusCode) {
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                422 => 'Unprocessable Entity',
                429 => 'Too Many Requests',
                500 => 'Internal Server Error',
                503 => 'Service Unavailable',
                default => 'An error occurred',
            };
        }

        return $exception->getMessage() ?: 'An error occurred';
    }

    protected function getCleanedTrace(Throwable $exception): array
    {
        $trace = $exception->getTrace();
        $cleaned = [];

        foreach ($trace as $index => $frame) {
            $cleaned[] = [
                'file'     => $frame['file'] ?? '[internal]',
                'line'     => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];

            if ($index >= 9) {
                break;
            }
        }

        return $cleaned;
    }

    protected function clearOutputBuffers(): void
    {
        while (ob_get_level() > $this->obLevel) {
            ob_end_clean();
        }
    }
}
