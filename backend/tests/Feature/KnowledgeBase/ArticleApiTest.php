<?php

namespace Tests\Feature\KnowledgeBase;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithSupportDomain;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

class ArticleApiTest extends TestCase
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
        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();
        parent::tearDown();
    }

    public function test_creating_an_article_forces_the_draft_state(): void
    {
        $this->bootSupportWorkspace();

        $response = $this->postJson('/api/v1/knowledge-base/articles', [
            'title' => 'How to reset your password',
            'content' => '<p>Use the forgot password link.</p>',
            'visibility' => 'ai',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'how-to-reset-your-password');

        $row = DB::connection('tenant')->table('knowledge_base_articles')->first();
        $this->assertSame('draft', $row->status);
        $this->assertNotNull($row->author_id);
    }

    public function test_article_title_content_and_visibility_are_required(): void
    {
        $this->bootSupportWorkspace();

        $this->postJson('/api/v1/knowledge-base/articles', ['title' => 'Only title'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content', 'visibility']]);

        $this->postJson('/api/v1/knowledge-base/articles', [
            'title' => 'Full',
            'content' => 'body',
            'visibility' => 'not-a-visibility',
        ])->assertStatus(422);

        $this->assertSame(0, DB::connection('tenant')->table('knowledge_base_articles')->count());
    }

    public function test_creating_an_article_with_an_unknown_category_is_rejected(): void
    {
        $this->bootSupportWorkspace();

        $this->postJson('/api/v1/knowledge-base/articles', [
            'title' => 'Orphan',
            'content' => 'body',
            'visibility' => 'ai',
            'category_id' => 999999,
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['category_id']]);
    }

    public function test_an_article_follows_the_publish_unpublish_archive_transitions(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->createDraftArticle();

        $this->postJson("/api/v1/knowledge-base/articles/{$id}/publish")
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertNotNull(DB::connection('tenant')->table('knowledge_base_articles')->where('id', $id)->value('published_at'));

        $this->postJson("/api/v1/knowledge-base/articles/{$id}/unpublish")
            ->assertOk()->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/v1/knowledge-base/articles/{$id}/publish")->assertOk();
        $this->postJson("/api/v1/knowledge-base/articles/{$id}/archive")
            ->assertOk()->assertJsonPath('data.status', 'archived');
    }

    public function test_an_invalid_article_transition_is_rejected(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->createDraftArticle();
        DB::connection('tenant')->table('knowledge_base_articles')->where('id', $id)->update(['status' => 'archived']);

        $this->postJson("/api/v1/knowledge-base/articles/{$id}/publish")->assertStatus(422);
        $this->assertSame('archived', DB::connection('tenant')->table('knowledge_base_articles')->where('id', $id)->value('status'));
    }

    public function test_articles_can_be_updated_and_deleted(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->createDraftArticle();

        $this->putJson("/api/v1/knowledge-base/articles/{$id}", ['title' => 'Updated title'])
            ->assertOk()->assertJsonPath('data.title', 'Updated title');

        $this->deleteJson("/api/v1/knowledge-base/articles/{$id}")->assertOk();
        $this->assertSoftDeleted('knowledge_base_articles', ['id' => $id], 'tenant');
    }

    public function test_ai_search_is_broken_because_the_controller_passes_the_query_bag(): void
    {
        $this->bootSupportWorkspace();
        $published = $this->createDraftArticle('Reset password', 'ai');
        $this->postJson("/api/v1/knowledge-base/articles/{$published}/publish")->assertOk();

        // Known defect: ArticleController::searchForAI() calls
        // KnowledgeBaseArticleService::getArticlesForAI($request->query) — the
        // whole InputBag — instead of `$request->query('query')`, so a type
        // error is swallowed into a 500. Production business logic is
        // intentionally left untouched; recorded in the test matrix.
        $this->getJson('/api/v1/knowledge-base/articles/search/ai?query=password')->assertStatus(500);
    }

    public function test_ai_search_requires_a_query(): void
    {
        $this->bootSupportWorkspace();

        // Known defect: searchForAI() validates inline but catches the
        // ValidationException in a broad `catch (\Exception)`, so a missing
        // query surfaces as 500 instead of 422. Documented in the test matrix;
        // production business logic is intentionally left untouched.
        $this->getJson('/api/v1/knowledge-base/articles/search/ai')->assertStatus(500);
    }

    public function test_an_article_from_another_tenant_is_not_reachable(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->createDraftArticle();

        $tenantB = $this->newTenant();
        $ownerB = $this->newTenantUser($tenantB, 'owner');
        $this->newSubscription($tenantB);
        $this->establishTenantContext($tenantB, $ownerB);

        $this->getJson("/api/v1/knowledge-base/articles/{$id}")->assertStatus(404);
        $this->getJson('/api/v1/knowledge-base/articles')->assertOk()->assertJsonCount(0, 'data');
    }

    private function createDraftArticle(string $title = 'Reset password', string $visibility = 'ai'): int
    {
        return (int) $this->postJson('/api/v1/knowledge-base/articles', [
            'title' => $title,
            'content' => '<p>Reset instructions</p>',
            'visibility' => $visibility,
        ])->assertStatus(201)->json('data.id');
    }
}
