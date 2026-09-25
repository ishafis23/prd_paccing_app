<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\TeknisiExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable middleware for testing
        $this->withoutMiddleware();

        // Create roles
        Role::create(['name' => RoleName::Teknisi->value, 'guard_name' => 'web']);
        Role::create(['name' => RoleName::Admin->value, 'guard_name' => 'web']);

        $this->teknisi = User::factory()->withRole('teknisi')->create();
        $this->admin = User::factory()->withRole('admin')->create();
    }

    /** @test */
    public function teknisi_can_create_expense()
    {
        $this->actingAs($this->teknisi);

        $response = $this->postJson('/teknisi/expense', [
            'tanggal_input' => today()->format('Y-m-d'),
            'kategori' => 'bensin',
            'nominal' => 100000,
            'keterangan' => 'Bensin perjalanan ke lokasi customer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['id', 'teknisi_id', 'status']]);

        $this->assertDatabaseHas('teknis_expenses', [
            'teknisi_id' => $this->teknisi->id,
            'kategori' => 'bensin',
            'nominal' => 100000,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function expense_validation_requires_fields()
    {
        $this->actingAs($this->teknisi);

        $response = $this->postJson('/teknisi/expense', [
            'kategori' => 'bensin',
            // missing tanggal_input, nominal
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_input', 'nominal']);
    }

    /** @test */
    public function expense_nominal_must_be_minimum()
    {
        $this->actingAs($this->teknisi);

        $response = $this->postJson('/teknisi/expense', [
            'tanggal_input' => today()->format('Y-m-d'),
            'kategori' => 'bensin',
            'nominal' => 500, // Less than minimum 1000
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nominal']);
    }

    /** @test */
    public function teknisi_can_only_see_own_expenses()
    {
        $other_teknisi = User::factory()->withRole('teknisi')->create();

        // Create expense for teknisi1
        TeknisiExpense::create([
            'teknisi_id' => $this->teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'bensin',
            'nominal' => 100000,
            'status' => 'pending',
        ]);

        // Create expense for teknisi2
        TeknisiExpense::create([
            'teknisi_id' => $other_teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'makan',
            'nominal' => 50000,
            'status' => 'pending',
        ]);

        // teknisi1 should only see their own
        $this->actingAs($this->teknisi);
        $response = $this->getJson('/teknisi/expenses');

        $response->assertStatus(200);
        $expenses = $response->json('data');
        $this->assertEquals(1, count($expenses));
        $this->assertEquals($this->teknisi->id, $expenses[0]['teknisi_id']);
    }

    /** @test */
    public function admin_can_approve_expense()
    {
        $expense = TeknisiExpense::create([
            'teknisi_id' => $this->teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'bensin',
            'nominal' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson("/admin/expense/$expense->id/approve", [
            'nominal' => 100000,
            'catatan_approval' => 'Approved',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['status', 'approved_by']]);

        $this->assertDatabaseHas('teknis_expenses', [
            'id' => $expense->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);
    }

    /** @test */
    public function admin_can_reject_expense()
    {
        $expense = TeknisiExpense::create([
            'teknisi_id' => $this->teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'bensin',
            'nominal' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson("/admin/expense/$expense->id/reject", [
            'catatan_approval' => 'Receipt not provided',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('teknis_expenses', [
            'id' => $expense->id,
            'status' => 'rejected',
            'catatan_approval' => 'Receipt not provided',
        ]);
    }

    /** @test */
    public function teknisi_cannot_edit_approved_expense()
    {
        $expense = TeknisiExpense::create([
            'teknisi_id' => $this->teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'bensin',
            'nominal' => 100000,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        $this->actingAs($this->teknisi);

        $response = $this->putJson("/teknisi/expense/$expense->id", [
            'nominal' => 150000,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function daily_summary_returns_correct_totals()
    {
        // Create pending expense
        TeknisiExpense::create([
            'teknisi_id' => $this->teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'bensin',
            'nominal' => 100000,
            'status' => 'pending',
        ]);

        // Create approved expense
        TeknisiExpense::create([
            'teknisi_id' => $this->teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'makan',
            'nominal' => 50000,
            'status' => 'approved',
        ]);

        $this->actingAs($this->teknisi);

        $response = $this->getJson('/teknisi/expense-summary/daily?date=' . today()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['date', 'total_pending', 'total_approved']]);

        $this->assertEquals(100000, $response->json('data.total_pending'));
        $this->assertEquals(50000, $response->json('data.total_approved'));
    }

    /** @test */
    public function monthly_summary_aggregates_correctly()
    {
        // Create expenses across month
        for ($day = 1; $day <= 5; $day++) {
            TeknisiExpense::create([
                'teknisi_id' => $this->teknisi->id,
                'tanggal_input' => today()->subDays(5 - $day),
                'kategori' => 'bensin',
                'nominal' => 100000,
                'status' => 'approved',
            ]);
        }

        $this->actingAs($this->teknisi);

        $response = $this->getJson('/teknisi/expense-summary/monthly?month=' . today()->format('Y-m'));

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $this->assertEquals(500000, $response->json('data.total_approved'));
    }

    /** @test */
    public function teknisi_can_delete_pending_expense()
    {
        $expense = TeknisiExpense::create([
            'teknisi_id' => $this->teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'bensin',
            'nominal' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($this->teknisi);

        $response = $this->deleteJson("/teknisi/expense/$expense->id");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('teknis_expenses', ['id' => $expense->id]);
    }

    /** @test */
    public function teknisi_cannot_delete_approved_expense()
    {
        $expense = TeknisiExpense::create([
            'teknisi_id' => $this->teknisi->id,
            'tanggal_input' => today(),
            'kategori' => 'bensin',
            'nominal' => 100000,
            'status' => 'approved',
        ]);

        $this->actingAs($this->teknisi);

        $response = $this->deleteJson("/teknisi/expense/$expense->id");

        $response->assertStatus(422);

        // Verify not deleted
        $this->assertDatabaseHas('teknis_expenses', ['id' => $expense->id]);
    }
}
