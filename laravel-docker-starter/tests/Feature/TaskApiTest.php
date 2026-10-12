<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_it_creates_a_task(): void
    {
        $response = $this->postJson('/api/tasks', [
            'title' => 'Migrate the legacy app to Docker',
            'due_date' => '2026-12-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Migrate the legacy app to Docker')
            ->assertJsonPath('data.done', false)
            ->assertJsonPath('data.due_date', '2026-12-01');

        $this->assertDatabaseHas('tasks', ['title' => 'Migrate the legacy app to Docker']);
    }

    public function test_title_is_required(): void
    {
        $this->postJson('/api/tasks', ['description' => 'no title'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_it_lists_and_filters_by_done(): void
    {
        Task::factory()->count(2)->create();
        Task::factory()->done()->create();

        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/tasks?done=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?done=0')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_it_updates_a_task(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}", ['done' => true])
            ->assertOk()
            ->assertJsonPath('data.done', true);

        $this->assertTrue($task->fresh()->done);
    }

    public function test_it_deletes_a_task(): void
    {
        $task = Task::factory()->create();

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($task);
    }

    public function test_missing_task_returns_404(): void
    {
        $this->getJson('/api/tasks/999')->assertNotFound();
    }
}
