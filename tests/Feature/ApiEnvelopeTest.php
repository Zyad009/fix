<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiEnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/testing')->group(function (): void {
            Route::get('ok', fn () => EnvelopeTestController::response(['id' => 1], 'done'));
            Route::get('business-rule', fn () => throw new BusinessRuleException('rule broken', 409, ['remaining' => 10], 'some_rule'));
            Route::get('crash', fn () => throw new RuntimeException('secret internals'));
            Route::post('validate', fn (EnvelopeTestRequest $request) => EnvelopeTestController::response($request->validated()));
        });
    }

    public function test_success_envelope(): void
    {
        $this->getJson('/api/testing/ok')
            ->assertOk()
            ->assertExactJson(['status' => true, 'msg' => 'done', 'data' => ['id' => 1]]);
    }

    public function test_unknown_route_returns_arabic_404_by_default(): void
    {
        $this->getJson('/api/v1/center/does-not-exist')
            ->assertNotFound()
            ->assertJson(['status' => false, 'msg' => __('messages.not_found', [], 'ar')]);
    }

    public function test_lang_header_switches_to_english(): void
    {
        $this->getJson('/api/v1/center/does-not-exist', ['lang' => 'en'])
            ->assertNotFound()
            ->assertJson(['msg' => 'Record not found.']);
    }

    public function test_unsupported_lang_falls_back_to_arabic(): void
    {
        $this->getJson('/api/v1/center/does-not-exist', ['lang' => 'xx'])
            ->assertJson(['msg' => __('messages.not_found', [], 'ar')]);
    }

    public function test_business_rule_exception_uses_its_status_and_payload(): void
    {
        $this->getJson('/api/testing/business-rule')
            ->assertStatus(409)
            ->assertExactJson([
                'status' => false,
                'msg' => 'rule broken',
                'code' => 'some_rule',
                'data' => ['remaining' => 10],
            ]);
    }

    public function test_unexpected_errors_are_masked_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/testing/crash')
            ->assertStatus(500)
            ->assertJson(['status' => false, 'msg' => __('messages.main_message_error', [], 'ar')])
            ->assertDontSee('secret internals');
    }

    public function test_validation_errors_use_the_form_request_envelope(): void
    {
        $this->postJson('/api/testing/validate', ['name' => ''])
            ->assertStatus(422)
            ->assertJson(['status' => 0, 'code' => 422, 'data' => null])
            ->assertJsonStructure(['message']);
    }
}

class EnvelopeTestController extends Controller {}

class EnvelopeTestRequest extends AbstractFormRequest
{
    protected function requireAtLeastOneField(): bool
    {
        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }
}
