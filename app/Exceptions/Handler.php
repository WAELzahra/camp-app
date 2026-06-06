<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Log 5xx errors with context for monitoring
            if ($this->isHttpException($e)) {
                $status = $e->getStatusCode();
                if ($status >= 500) {
                    Log::channel('security')->critical('Server error', [
                        'exception' => get_class($e),
                        'message'   => $e->getMessage(),
                        'url'       => request()->fullUrl(),
                        'ip'        => request()->ip(),
                        'user_id'   => auth()->id(),
                    ]);
                }
            }
        });
    }

    public function render($request, Throwable $e)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    private function renderApiException($request, Throwable $e)
    {
        if ($e instanceof ValidationException) {
            $errors = $e->errors();

            // Produce the required image-error shape when the only failure is an
            // image field exceeding the 5 MB limit, so the client gets a clear
            // { message, field } response rather than a generic validation bag.
            $imageFields = ['avatar', 'cover_image', 'cin_file', 'photo', 'photos'];
            foreach ($imageFields as $field) {
                // Check both singular and array-upload patterns (photos.*)
                foreach ($errors as $key => $messages) {
                    $base = explode('.', $key)[0];
                    if ($base === $field && str_contains(implode(' ', $messages), '5')) {
                        return response()->json([
                            'message' => 'Image must be under 5MB.',
                            'field'   => $field,
                        ], 422);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors'  => $errors,
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please log in.',
                'error'   => 'unauthenticated',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'error'   => 'forbidden',
            ], 403);
        }

        if ($e instanceof QueryException) {
            Log::error('Database error', [
                'sql'     => $e->getSql(),
                'message' => $e->getMessage(),
                'url'     => request()->fullUrl(),
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'error'   => 'database_error',
            ], 500);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'error'   => 'not_found',
            ], 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed.',
                'error'   => 'method_not_allowed',
            ], 405);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'HTTP error.',
                'error'   => 'http_error',
            ], $e->getStatusCode());
        }

        // Never expose stack traces or internal details to clients
        Log::error('Unhandled exception', [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'url'       => request()->fullUrl(),
            'ip'        => request()->ip(),
            'user_id'   => auth()->id(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again later.',
            'error'   => 'server_error',
        ], 500);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please log in.',
                'error'   => 'unauthenticated',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
