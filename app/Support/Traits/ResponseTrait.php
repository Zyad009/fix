<?php

namespace App\Support\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

trait ResponseTrait
{
    /**
     * Build the unified API envelope: {status, msg, data, pagination?}.
     */
    public static function response(mixed $data, ?string $msg = '', int|string|null $statusCode = 200, ?array $pagination = null): JsonResponse
    {
        $httpStatusCode = is_numeric($statusCode) ? (int) $statusCode : 500;

        if ($httpStatusCode < 100 || $httpStatusCode > 599) {
            $httpStatusCode = 500;
        }

        if ($httpStatusCode === 500 && ! config('app.debug')) {
            $msg = __('messages.main_message_error');
        }

        $response = [
            'status' => $httpStatusCode <= 204,
            'msg' => $msg ?? '',
            'data' => $data,
        ];

        if ($pagination) {
            $response['pagination'] = $pagination;
        }

        return response()->json($response, $httpStatusCode);
    }

    /**
     * Respond with a paginated resource collection and its pagination meta.
     *
     * @param  class-string<JsonResource>  $resource
     */
    public function paginate(object $collection, string $resource, ?string $message = '', int $statusCode = 200): JsonResponse
    {
        return self::response($resource::collection($collection), $message, $statusCode, self::formatPagination($collection));
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int, has_more_pages: bool, next_page_url: ?string, prev_page_url: ?string}|array{}
     */
    protected static function formatPagination(object $collection): array
    {
        if ($collection instanceof AnonymousResourceCollection) {
            $collection = $collection->resource;
        }

        if (! $collection instanceof LengthAwarePaginator) {
            return [];
        }

        return [
            'current_page' => $collection->currentPage(),
            'per_page' => $collection->perPage(),
            'total' => $collection->total(),
            'last_page' => $collection->lastPage(),
            'has_more_pages' => $collection->hasMorePages(),
            'next_page_url' => $collection->nextPageUrl(),
            'prev_page_url' => $collection->previousPageUrl(),
        ];
    }
}
