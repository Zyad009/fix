<?php

namespace Tests\Feature\Center;

use App\Models\Category;
use App\Models\ProductBranchStock;
use Tests\CenterHttpTestCase;

class CatalogTest extends CenterHttpTestCase
{
    public function test_owner_manages_products_and_cashier_cannot(): void
    {
        $category = Category::query()->where('name', 'زيوت')->firstOrFail();

        $productId = $this->postJson($this->url('products'), [
            'category_id' => $category->id,
            'name' => 'زيت موبيل 1',
            'kind' => 'part',
            'sale_price' => 450,
            'cost_price' => 380,
            'track_stock' => true,
        ], $this->as($this->owner))
            ->assertCreated()
            ->assertJsonPath('data.cost_price', 380)
            ->json('data.id');

        $this->postJson($this->url('products'), ['name' => 'x', 'kind' => 'part', 'sale_price' => 1], $this->as($this->cashier()))->assertForbidden();

        $this->getJson($this->url("products/{$productId}"), $this->as($this->cashier()))
            ->assertOk()
            ->assertJsonMissingPath('data.cost_price');
    }

    public function test_services_never_track_stock(): void
    {
        $this->postJson($this->url('products'), [
            'name' => 'مصنعية', 'kind' => 'service', 'sale_price' => 100, 'track_stock' => true,
        ], $this->as($this->owner))
            ->assertCreated()
            ->assertJsonPath('data.track_stock', false);
    }

    public function test_pos_catalog_lists_active_categories_and_products_with_branch_stock(): void
    {
        $oil = Category::query()->where('name', 'زيوت')->firstOrFail();
        $product = $this->product(['category_id' => $oil->id]);
        $this->product(['category_id' => $oil->id, 'name' => 'موقوف', 'is_active' => false]);

        $this->patchJson($this->url("products/{$product->id}/stock"), ['qty' => 12, 'reason' => 'جرد'], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.stock', 12);

        $response = $this->getJson($this->url('pos/catalog'), $this->as($this->cashier()))->assertOk();
        $oilCategory = collect($response->json('data'))->firstWhere('name', 'زيوت');

        $this->assertCount(1, $oilCategory['products']);
        $this->assertSame(12, $oilCategory['products'][0]['stock']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'product', 'event' => 'stock_adjusted', 'reason' => 'جرد']);
    }

    public function test_stock_is_per_branch(): void
    {
        $second = $this->createBranch();
        $product = $this->product();

        $this->patchJson($this->url("products/{$product->id}/stock"), ['qty' => 5], $this->as($this->owner, $this->branch))->assertOk();
        $this->patchJson($this->url("products/{$product->id}/stock"), ['qty' => 9], $this->as($this->owner, $second))->assertOk();

        $this->getJson($this->url("products/{$product->id}"), $this->as($this->owner, $second))->assertJsonPath('data.stock', 9);
        $this->getJson($this->url("products/{$product->id}"), $this->as($this->owner))->assertJsonPath('data.stock', 14);

        $this->inTenant($this->tenant);
        $this->assertSame(2, ProductBranchStock::query()->count());
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::query()->firstOrFail();
        $this->product(['category_id' => $category->id]);

        $this->deleteJson($this->url("categories/{$category->id}"), [], $this->as($this->owner))->assertStatus(409);

        $empty = Category::query()->where('id', '!=', $category->id)->firstOrFail();
        $this->deleteJson($this->url("categories/{$empty->id}"), [], $this->as($this->owner))->assertOk();
    }

    public function test_category_names_are_unique_per_tenant(): void
    {
        $this->postJson($this->url('categories'), ['name' => 'زيوت'], $this->as($this->owner))->assertStatus(422);
        $this->postJson($this->url('categories'), ['name' => 'إكسسوارات'], $this->as($this->owner))->assertCreated();
    }
}
