<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exports\ArrayExport;
use App\Support\Traits\ResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

abstract class Controller
{
    use ResponseTrait;

    /**
     * Normalise a caught throwable into a JSON response.
     *
     * Renderable framework/domain exceptions are re-thrown so the global
     * handler renders them with their correct status code (401/403/404/422/…).
     * Everything else is logged and returned as a masked 500.
     */
    protected function failed(\Throwable $th): JsonResponse
    {
        if (
            $th instanceof BusinessRuleException
            || $th instanceof HttpResponseException
            || $th instanceof ValidationException
            || $th instanceof ModelNotFoundException
            || $th instanceof AuthenticationException
            || $th instanceof AuthorizationException
            || $th instanceof HttpExceptionInterface
        ) {
            throw $th;
        }

        log_error($th);

        return $this->response([], $th->getMessage(), 500);
    }

    protected function wantsExcel(Request $request): bool
    {
        return $request->query('format') === 'excel';
    }

    /**
     * Download rows as a right-to-left Excel sheet.
     *
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    protected function excel(string $filename, array $headings, array $rows): BinaryFileResponse
    {
        return Excel::download(new ArrayExport($headings, $rows), $filename.'-'.now()->format('Y-m-d').'.xlsx');
    }
}
