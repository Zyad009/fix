<?php

/*
 * Reads what each endpoint accepts and who may call it straight from the
 * code (FormRequest rules + route middleware + config/rbac.php), so the
 * generated docs can never drift from the API.
 */

use App\Models\MaintenanceCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;

/**
 * The FormRequest an action validates with, if any.
 *
 * @return class-string<FormRequest>|null
 */
function requestClassFor(Route $route): ?string
{
    $action = $route->getActionName();

    if (! str_contains($action, '@')) {
        return null;
    }

    [$controller, $method] = explode('@', $action);

    foreach ((new ReflectionMethod($controller, $method))->getParameters() as $parameter) {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), FormRequest::class)) {
            return $type->getName();
        }
    }

    return null;
}

/**
 * Field => rules of the endpoint's FormRequest, built as the real request
 * would be (same HTTP method, route bound, a placeholder maintenance center in context).
 *
 * @param  array<string, mixed>  $input  input that switches conditional rules on
 * @return array<string, list<mixed>>
 */
function requestRules(Route $route, array $input = []): array
{
    $class = requestClassFor($route);

    if ($class === null) {
        return [];
    }

    $method = $route->methods()[0];
    $request = $class::create('/'.$route->uri(), $method, $input);
    $request->setContainer(app());
    $route->bind($request);
    bindPlaceholderModels($route);
    $request->setRouteResolver(fn () => $route);

    if (! maintenanceCenterContext()->check()) {
        $maintenanceCenter = new MaintenanceCenter;
        $maintenanceCenter->id = 0;
        maintenanceCenterContext()->set($maintenanceCenter);
    }

    $rules = $request->rules();

    return array_map(fn (mixed $rule): array => is_string($rule) ? explode('|', $rule) : (array) $rule, $rules);
}

/**
 * Route-model parameters become empty models (rules may read them, e.g. the
 * parent maintenance center of a branch), instead of the raw "{placeholder}" text.
 */
function bindPlaceholderModels(Route $route): void
{
    [$controller, $method] = explode('@', $route->getActionName());

    $parameters = collect((new ReflectionMethod($controller, $method))->getParameters())
        ->keyBy(fn (ReflectionParameter $parameter): string => $parameter->getName());

    foreach ($route->parameterNames() as $name) {
        $type = $parameters->get(Str::camel($name))?->getType();

        if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Model::class)) {
            $model = new ($type->getName());
            $model->id = 0;
            $route->setParameter($name, $model);
        }
    }
}

/**
 * Human-readable form of one rule.
 */
function describeRule(mixed $rule): ?string
{
    if (is_string($rule)) {
        return preg_replace('/^(exists|unique):([^,]+).*/', '$1:$2', $rule);
    }

    if ($rule instanceof Enum) {
        $type = (new ReflectionProperty($rule, 'type'))->getValue($rule);

        return 'in: '.implode(', ', array_map(fn (BackedEnum $case): string => (string) $case->value, $type::cases()));
    }

    if ($rule instanceof Stringable) {
        $text = (string) $rule;

        if (str_starts_with($text, 'in:')) {
            return 'in: '.str_replace('"', '', substr($text, 3));
        }

        return preg_replace('/^(exists|unique):([^,]+).*/', '$1:$2', $text);
    }

    return null;
}

/**
 * Rules of every mode of the endpoint merged: a field prohibited in one mode
 * (e.g. `orders` unless allocation=manual) is taken from the mode allowing it.
 *
 * @param  list<array<string, mixed>>  $inputs
 * @return array<string, list<mixed>>
 */
function mergedRules(Route $route, array $inputs): array
{
    $merged = [];

    foreach ($inputs === [] ? [[]] : $inputs as $input) {
        foreach (requestRules($route, $input) as $field => $rules) {
            if (! isset($merged[$field]) || in_array('prohibited', array_filter($merged[$field], 'is_string'), true)) {
                $merged[$field] = $rules;
            }
        }
    }

    return $merged;
}

/**
 * Markdown table of the endpoint's fields.
 *
 * @param  array<string, list<mixed>>  $rules
 * @param  list<string>|null  $only  top-level fields the endpoint really reads
 * @param  array<string, string>  $notes  field => note (a "default:" note makes it optional)
 */
function fieldsTable(array $rules, ?array $only = null, array $notes = []): ?string
{
    $rows = [];
    $prohibited = [];

    foreach ($rules as $field => $fieldRules) {
        $texts = array_values(array_filter(array_map('describeRule', $fieldRules)));
        $names = array_map(fn (string $text): string => explode(':', $text)[0], $texts);
        $root = explode('.', $field)[0];

        if (in_array('prohibited', $names, true)) {
            $prohibited[] = $field;

            continue;
        }

        if (str_contains($field, 'plate_normalized')
            || ($only !== null && ! in_array($root, $only, true))
            || array_filter($prohibited, fn (string $parent): bool => str_starts_with($field, $parent.'.'))) {
            continue;
        }

        $note = $notes[$field] ?? null;

        $presence = match (true) {
            $note !== null && str_starts_with($note, 'default:') => '—',
            $note !== null && str_ends_with($note, ' only') => '⚠️',
            in_array('required', $names, true) && ! in_array('sometimes', $names, true) => '✅',
            (bool) preg_grep('/^(required_|prohibited_|prohibits)/', $names) => '⚠️',
            default => '—',
        };

        $shown = array_filter($texts, fn (string $text): bool => ! preg_match('/^(required|sometimes|nullable|bail|present|exclude_without|exclude_unless)$|^exclude_without:/', $text));
        $shown[] = in_array('nullable', $names, true) ? 'nullable' : null;
        $shown[] = $note !== null ? '**'.$note.'**' : null;

        $rows[] = '| `'.$field.'` | '.$presence.' | '.str_replace('|', '\|', implode(' · ', array_filter($shown))).' |';
    }

    if ($rows === []) {
        return null;
    }

    return '| Field | Required | Rules |
|---|:-:|---|
'.implode('
', $rows)
        .'

✅ مطلوب / required · ⚠️ مطلوب بشرط / conditional · — اختياري / optional.'
        .(array_filter($rows, fn (string $row): bool => str_contains($row, '.*.'))
            ? ' الحقول اللي فيها `.*.` جوه كل عنصر في الـ list، و ✅ عليها يعني مطلوبة جوه العنصر / `.*.` fields belong to each list item; ✅ there means required within the item.'
            : '');
}

/**
 * Who may call the endpoint: auth guard + permission → roles (config/rbac.php).
 *
 * @return array{auth: string, permission: ?string, roles: list<string>}
 */
function accessFor(Route $route): array
{
    $middleware = $route->gatherMiddleware();
    $auth = 'none (public)';

    if (in_array('auth:sanctum', $middleware, true)) {
        $auth = str_starts_with($route->uri(), 'api/v1/admin') ? 'Bearer token (admin)' : 'Bearer token (center)';
    }

    $permission = null;

    foreach ($middleware as $item) {
        if (is_string($item) && str_starts_with($item, 'permission:')) {
            $permission = substr($item, strlen('permission:'));
        }
    }

    $roles = [];

    if ($permission !== null) {
        $wanted = explode('|', $permission);

        foreach (config('rbac.roles') as $role => $granted) {
            if (in_array('*', $granted, true) || array_intersect($wanted, $granted)) {
                $roles[] = $role;
            }
        }
    } elseif (str_starts_with($route->uri(), 'api/v1/center') && $auth !== 'none (public)') {
        $roles = array_keys(config('rbac.roles'));
    } elseif (str_starts_with($route->uri(), 'api/v1/admin') && $auth !== 'none (public)') {
        $roles = ['super admin'];
    }

    return ['auth' => $auth, 'permission' => $permission, 'roles' => $roles];
}
