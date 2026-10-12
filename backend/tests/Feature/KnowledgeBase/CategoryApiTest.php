<?php

namespace Tests\Feature\KnowledgeBase;

use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithSupportDomain;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use InteractsWithCentralSchema;
    use InteractsWithSupportDomain;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCentralSchema();
        $this->useIsolatedTenantDatabase();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();
        parent::tearDown();
    }

    public function test_a_category_is_created_with_a_generated_slug(): void
    {
        $this->bootSupportWorkspace();

        $response = $this->postJson('/api/v1/knowledge-base/categories', [
            'name' => 'Getting Started',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Getting Started')
            ->assertJsonPath('data.slug', 'getting-started');

        // The DB default is authoritative here; Eloquent's create() does not
        // hydrate the column default back onto the returned model.
        $this->assertSame(
            'active',
            DB::connection('tenant')->table('knowledge_base_categories')->where('id', $response->json('data.id'))->value('status')
        );
    }

    public function test_a_category_requires_a_name(): void
    {
        $this->bootSupportWorkspace();

        $this->postJson('/api/v1/knowledge-base/categories', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name']]);

        $this->assertSame(0, DB::connection('tenant')->table('knowledge_base_categories')->count());
    }

    public function test_duplicate_category_slugs_are_rejected(): void
    {
        $this->bootSupportWorkspace();

        $this->postJson('/api/v1/knowledge-base/categories', ['name' => 'Billing', 'slug' => 'billing'])->assertStatus(201);
        $this->postJson('/api/v1/knowledge-base/categories', ['name' => 'Billing 2', 'slug' => 'billing'])->assertStatus(422);

        $this->assertSame(1, DB::connection('tenant')->table('knowledge_base_categories')->count());
    }

    public function test_categories_can_be_listed_updated_activated_and_deactivated(): void
    {
        $this->bootSupportWorkspace();

        $id = $this->postJson('/api/v1/knowledge-base/categories', ['name' => 'FAQ'])->json('data.id');

        $this->getJson('/api/v1/knowledge-base/categories')->assertOk();
        $this->getJson("/api/v1/knowledge-base/categories/{$id}")->assertOk()->assertJsonPath('data.name', 'FAQ');

        $this->putJson("/api/v1/knowledge-base/categories/{$id}", ['name' => 'FAQs'])
            ->assertOk()->assertJsonPath('data.name', 'FAQs');

        $this->postJson("/api/v1/knowledge-base/categories/{$id}/deactivate")
            ->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->postJson("/api/v1/knowledge-base/categories/{$id}/activate")
            ->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->postJson('/api/v1/knowledge-base/categories', ['name' => 'Temp'])->json('data.id');

        $this->deleteJson("/api/v1/knowledge-base/categories/{$id}")->assertOk();
        $this->assertSoftDeleted('knowledge_base_categories', ['id' => $id], 'tenant');
    }

    public function test_a_category_with_articles_cannot_be_deleted(): void
    {
        $this->bootSupportWorkspace();
        $categoryId = $this->postJson('/api/v1/knowledge-base/categories', ['name' => 'Busy'])->json('data.id');
        DB::connection('tenant')->table('knowledge_base_articles')->insert([
            'category_id' => $categoryId,
            'title' => 'Doc',
            'slug' => 'doc',
            'content' => '<p>body</p>',
            'status' => 'draft',
            'visibility' => 'ai',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/api/v1/knowledge-base/categories/{$categoryId}")->assertStatus(422);
        $this->assertDatabaseHas('knowledge_base_categories', ['id' => $categoryId, 'deleted_at' => null], 'tenant');
    }

    public function test_a_category_from_another_tenant_is_not_reachable(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->postJson('/api/v1/knowledge-base/categories', ['name' => 'Private'])->json('data.id');

        $tenantB = $this->newTenant();
        $ownerB = $this->newTenantUser($tenantB, 'owner');
        $this->newSubscription($tenantB);
        $this->establishTenantContext($tenantB, $ownerB);

        $this->getJson("/api/v1/knowledge-base/categories/{$id}")->assertStatus(404);
        $this->getJson('/api/v1/knowledge-base/categories')->assertOk()->assertJsonCount(0, 'data');
    }
}
