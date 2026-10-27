<?php

/*
 * Builds postman/Dawar.postman_collection.json (+ the local environment) from
 * the live application, so every endpoint is always there and its docs match
 * the code.
 *
 *     composer postman        (or: php postman/build/generate.php)
 *
 * Each request's "Docs" tab = the Arabic + English description (docs.php),
 * then the permission/roles and the fields table read from the code
 * (introspect.php). Example bodies and queries live in examples.php.
 *
 * After adding or changing an endpoint: describe it in docs.php (both
 * languages), add an example in examples.php, re-run, and commit the JSON.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

require __DIR__.'/introspect.php';
require __DIR__.'/docs.php';
require __DIR__.'/examples.php';

const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

/**
 * The full "Docs" tab of one request.
 *
 * @param  array{ar: string, en: string}|null  $text
 */
function documentation(Route $route, ?array $text, ?string $fields): string
{
    $access = accessFor($route);
    $roles = $access['roles'] === [] ? '—' : implode(', ', array_map(fn (string $role): string => '`'.$role.'`', $access['roles']));

    $parts = [];

    if ($text !== null) {
        $parts[] = "### 🇪🇬 العربية\n\n".trim($text['ar']);
        $parts[] = "### 🇬🇧 English\n\n".trim($text['en']);
    }

    $parts[] = "### 🔐 Access\n\n"
        ."| | |\n|---|---|\n"
        .'| Endpoint | `'.$route->methods()[0].' /'.$route->uri()."` |\n"
        .'| Auth / المصادقة | '.$access['auth']." |\n"
        .'| Permission / الصلاحية | '.($access['permission'] !== null ? '`'.$access['permission'].'`' : '—')." |\n"
        .'| Roles / الأدوار | '.$roles.' |';

    $location = in_array($route->methods()[0], ['GET', 'HEAD', 'DELETE'], true) ? 'Query' : 'Body';
    $parts[] = '### 📥 '.$location." parameters / المدخلات\n\n".($fields ?? '_لا يوجد / none_');

    return implode("\n\n---\n\n", $parts);
}

$folders = [];
$missing = [];

foreach (app('router')->getRoutes()->getRoutes() as $route) {
    if (! str_starts_with($route->uri(), 'api/v1/')) {
        continue;
    }

    $name = $route->getName();
    $method = $route->methods()[0];
    $path = substr($route->uri(), strlen('api/v1/'));
    [$module, $section] = array_pad(explode('/', $path), 2, '');
    $folder = $module.' / '.($section === 'auth' ? 'auth' : (explode('{', $section)[0] ?: $section));

    if (! isset($descriptions[$name])) {
        $missing[] = $name;
    }

    $fields = fieldsTable(
        mergedRules($route, isset($requestInput[$name]) ? [[], $requestInput[$name]] : []),
        $fieldsOnly[$name] ?? null,
        $fieldNotes[$name] ?? [],
    );

    $postmanPath = preg_replace('/\{(\w+)\??\}/', ':$1', $path);
    $query = $queries[$name] ?? null;

    $headers = [
        ['key' => 'Accept', 'value' => 'application/json'],
        ['key' => 'lang', 'value' => '{{lang}}', 'description' => 'ar (default) | en'],
    ];

    if ($module === 'center') {
        $headers[] = ['key' => 'X-Branch-Id', 'value' => '{{branch_id}}', 'disabled' => true, 'description' => 'Owner only: act in one branch / للـ owner: يشتغل على فرع معين'];
    }

    $item = [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => $headers,
            'url' => [
                'raw' => '{{base_url}}/'.$postmanPath.($query ? '?'.$query : ''),
                'host' => ['{{base_url}}'],
                'path' => explode('/', $postmanPath),
                'query' => $query ? array_map(fn (string $pair): array => array_combine(['key', 'value'], explode('=', $pair, 2)), explode('&', $query)) : [],
                'variable' => array_map(fn (string $var): array => ['key' => $var, 'value' => '1'], preg_match_all('/:(\w+)/', $postmanPath, $matches) ? $matches[1] : []),
            ],
            'auth' => str_ends_with($name, 'auth.login')
                ? ['type' => 'noauth']
                : ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => $module === 'admin' ? '{{admin_token}}' : '{{token}}', 'type' => 'string']]],
            'description' => documentation($route, $descriptions[$name] ?? null, $fields),
        ],
    ];

    if (isset($bodies[$name])) {
        $item['request']['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
        $item['request']['body'] = ['mode' => 'raw', 'raw' => json_encode($bodies[$name], JSON_FLAGS)];
    }

    if (str_ends_with($name, 'auth.login')) {
        $var = $module === 'admin' ? 'admin_token' : 'token';
        $item['event'] = [[
            'listen' => 'test',
            'script' => ['type' => 'text/javascript', 'exec' => array_values(array_filter([
                'const body = pm.response.json();',
                "if (body.status && body.data.token) { pm.collectionVariables.set('{$var}', body.data.token); }",
                $module === 'center' ? "if (body.data && body.data.branches && body.data.branches.length) { pm.collectionVariables.set('branch_id', body.data.branches[0].id); }" : null,
            ]))],
        ]];
    }

    foreach ($variants[$name] ?? [null] as $variant) {
        if ($variant === null) {
            $folders[$folder][] = $item;

            continue;
        }

        $copy = $item;
        $copy['name'] = $variant['name'];
        $copy['request']['header'] = [...array_filter($item['request']['header'], fn (array $header): bool => $header['key'] !== 'Content-Type'), ['key' => 'Content-Type', 'value' => 'application/json']];
        $copy['request']['body'] = ['mode' => 'raw', 'raw' => json_encode($variant['body'], JSON_FLAGS)];

        if (isset($variant['ar'])) {
            $copy['request']['description'] = documentation($route, ['ar' => $variant['ar'], 'en' => $variant['en']], $fields);
        }

        $folders[$folder][] = $copy;
    }
}

ksort($folders);

$overview = <<<'MD'
### 🇪🇬 العربية

API نظام إدارة مراكز صيانة العربيات (Dawar).

1. شغّل `center / auth → center.auth.login` (أو بتاع الأدمن): التوكن ورقم الفرع بيتحفظوا لوحدهم.
2. الـ owner يقدر يفعّل header `X-Branch-Id` عشان يشتغل على فرع معين؛ الكاشير والفني مربوطين بفرعهم.
3. header `lang`: `ar` (الافتراضي) أو `en` — بيغيّر لغة الرسائل والأسماء.
4. كل الردود بالشكل `{status, msg, data, pagination?}`. الأخطاء: `status: false` + `msg` (+ `code` أحياناً). 422 = validation، 402 = الاشتراك خلص (قراءة بس)، 403 = صلاحية، 404 = مش موجود (أو تبع مركز/فرع تاني).
5. تبويب **Docs** في كل request: الشرح عربي وإنجليزي + الصلاحية والأدوار + جدول المدخلات (متولّد من الكود).

**بيانات تجربة:** `php artisan db:seed --class=DemoSeeder` — الحسابات في README (كلمة السر `password`).

---

### 🇬🇧 English

Dawar — car service center management API.

1. Run `center / auth → center.auth.login` (or the admin one): the token and branch id are stored automatically.
2. Owners may enable the `X-Branch-Id` header to act in one branch; cashiers and technicians are pinned to theirs.
3. `lang` header: `ar` (default) or `en` — switches messages and labels.
4. Every response is `{status, msg, data, pagination?}`. Errors: `status: false` + `msg` (+ sometimes `code`). 422 = validation, 402 = subscription expired (read-only), 403 = permission, 404 = not found (or another center's/branch's).
5. Each request's **Docs** tab: Arabic + English description, permission and roles, and the parameters table (generated from the code).

**Demo data:** `php artisan db:seed --class=DemoSeeder` — accounts are listed in the README (password `password`).
MD;

$collection = [
    'info' => [
        'name' => 'Dawar API',
        'description' => $overview,
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'item' => array_map(fn (string $folder, array $items): array => ['name' => $folder, 'item' => $items], array_keys($folders), array_values($folders)),
    'variable' => [
        ['key' => 'base_url', 'value' => 'http://localhost:8000/api/v1'],
        ['key' => 'lang', 'value' => 'ar'],
        ['key' => 'token', 'value' => ''],
        ['key' => 'admin_token', 'value' => ''],
        ['key' => 'branch_id', 'value' => ''],
    ],
];

$environment = [
    'name' => 'Dawar Local',
    'values' => [
        ['key' => 'base_url', 'value' => 'http://localhost:8000/api/v1', 'enabled' => true],
        ['key' => 'lang', 'value' => 'ar', 'enabled' => true],
    ],
    '_postman_variable_scope' => 'environment',
];

file_put_contents($root.'/postman/Dawar.postman_collection.json', json_encode($collection, JSON_FLAGS)."\n");
file_put_contents($root.'/postman/Dawar-Local.postman_environment.json', json_encode($environment, JSON_FLAGS)."\n");

echo count($folders).' folders, '.array_sum(array_map('count', $folders))." requests\n";

if ($missing !== []) {
    fwrite(STDERR, "Undocumented endpoints (add them to docs.php):\n  ".implode("\n  ", $missing)."\n");
    exit(1);
}
