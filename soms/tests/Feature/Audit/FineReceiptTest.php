<?php
// tests/Feature/Audit/FineReceiptTest.php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/**
 * Receipt / OR number on mark-paid. Optional by default (so the mobile app
 * keeps working), required when configured, never accepted on a waiver.
 */
class FineReceiptTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    public function test_marking_paid_stores_the_receipt_number_and_logs_it(): void
    {
        $treasurer = $this->makeTreasurer();
        $fine = $this->makeFine($this->makeStudent());

        $this->actingAs($treasurer)->post(route('officer.fines.clear', $fine), [
            'reason' => 'Paid in person', 'receipt_number' => 'OR-2026-0101',
        ])->assertRedirect();

        $this->assertSame('OR-2026-0101', $fine->fresh()->receipt_number);
        $this->assertSame('paid', $fine->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'fine_cleared', 'model_id' => $fine->id]);
    }

    public function test_the_receipt_is_optional_by_default(): void
    {
        $fine = $this->makeFine($this->makeStudent());

        $this->actingAs($this->makeTreasurer())->post(route('officer.fines.clear', $fine), ['reason' => 'Paid in person'])
            ->assertRedirect();

        $this->assertSame('paid', $fine->fresh()->status);
        $this->assertNull($fine->fresh()->receipt_number);
    }

    public function test_the_receipt_is_required_when_the_system_is_set_to_require_it(): void
    {
        config(['soms.require_payment_receipt' => true]);
        $fine = $this->makeFine($this->makeStudent());

        $this->actingAs($this->makeTreasurer())->post(route('officer.fines.clear', $fine), ['reason' => 'Paid in person'])
            ->assertSessionHasErrors('receipt_number');

        $this->assertSame('unpaid', $fine->fresh()->status);
    }

    public function test_a_receipt_with_unsafe_characters_is_rejected(): void
    {
        $fine = $this->makeFine($this->makeStudent());

        foreach (['=CMD()', '<script>', '@home', "OR-1\nOR-2"] as $bad) {
            $this->actingAs($this->makeTreasurer())->post(route('officer.fines.clear', $fine), [
                'reason' => 'Paid in person', 'receipt_number' => $bad,
            ])->assertSessionHasErrors('receipt_number');
        }

        $this->assertSame('unpaid', $fine->fresh()->status);
    }

    public function test_one_receipt_is_recorded_on_every_fine_of_a_bulk_payment(): void
    {
        $student = $this->makeStudent();
        $first = $this->makeFine($student);
        $second = $this->makeFine($student, ['violation_type' => 'missed_morning_timeout', 'amount' => 30]);

        $this->actingAs($this->makeTreasurer())->post(route('officer.fines.clearAll', $student), [
            'reason' => 'Paid in person', 'receipt_number' => 'OR-2026-0200',
        ])->assertRedirect();

        $this->assertSame('OR-2026-0200', $first->fresh()->receipt_number);
        $this->assertSame('OR-2026-0200', $second->fresh()->receipt_number);
    }

    public function test_a_waiver_never_takes_a_receipt_number(): void
    {
        $fine = $this->makeFine($this->makeStudent());

        $this->actingAs($this->makeTreasurer())->post(route('officer.fines.waive', $fine), [
            'reason' => 'Excused by the adviser', 'receipt_number' => 'OR-1',
        ]);
        $this->assertNull($fine->fresh()->receipt_number);

        $student = $this->makeStudent('Another');
        $this->makeFine($student);
        $this->actingAs($this->makeTreasurer())->post(route('officer.fines.waiveAll', $student), [
            'reason' => 'Excused by the adviser', 'receipt_number' => 'OR-1',
        ])->assertSessionHasErrors('receipt_number');
    }

    public function test_the_mobile_api_stores_the_receipt_and_still_works_without_one(): void
    {
        Sanctum::actingAs($this->makeTreasurer(), ['*']);
        $withReceipt = $this->makeFine($this->makeStudent('One'));
        $without = $this->makeFine($this->makeStudent('Two'));

        $this->postJson("/api/v1/officer/fines/{$withReceipt->id}/clear", ['reason' => 'Paid in person', 'receipt_number' => 'OR-77'])
            ->assertOk();
        $this->postJson("/api/v1/officer/fines/{$without->id}/clear", ['reason' => 'Paid in person'])
            ->assertOk();

        $this->assertSame('OR-77', $withReceipt->fresh()->receipt_number);
        $this->assertNull($without->fresh()->receipt_number);
    }

    public function test_the_fines_screen_shows_the_receipt_number_of_a_paid_fine(): void
    {
        $this->makeFine($this->makeStudent(), [
            'status' => 'paid', 'cleared_by' => $this->admin->id, 'reason' => 'Paid in person', 'receipt_number' => 'OR-2026-0555',
        ]);

        $this->actingAs($this->makeTreasurer())->get(route('officer.fines.index'))
            ->assertOk()
            ->assertSee('OR-2026-0555');
    }
}
