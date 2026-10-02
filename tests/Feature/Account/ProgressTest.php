<?php

namespace Tests\Feature\Account;

use App\Models\Measurement;
use App\Models\WeightPlan;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    public function test_a_client_records_a_measurement(): void
    {
        $client = $this->client();

        $this->actingAs($client)->post(route('account.progress.store'), [
            'measured_on' => today()->toDateString(), 'weight_kg' => '91.4', 'waist_cm' => '102', 'note' => 'صباحًا',
        ])->assertRedirect(route('account.progress.index'));

        $measurement = Measurement::firstOrFail();
        $this->assertSame($client->id, $measurement->user_id);
        $this->assertSame(91.4, $measurement->weight_kg);
        $this->assertSame('client', $measurement->source);
    }

    public function test_measurement_input_is_validated(): void
    {
        $client = $this->actingAs($this->client());

        $client->post(route('account.progress.store'), ['measured_on' => today()->addDay()->toDateString(), 'weight_kg' => '90'])->assertSessionHasErrors('measured_on');
        $client->post(route('account.progress.store'), ['measured_on' => today()->toDateString(), 'weight_kg' => '5'])->assertSessionHasErrors('weight_kg');
        $client->post(route('account.progress.store'), ['measured_on' => today()->toDateString(), 'weight_kg' => 'abc'])->assertSessionHasErrors('weight_kg');
        $client->post(route('account.progress.store'), ['measured_on' => today()->toDateString(), 'weight_kg' => '90', 'waist_cm' => '900'])->assertSessionHasErrors('waist_cm');
        $client->post(route('account.progress.store'), ['measured_on' => '1990-01-01', 'weight_kg' => '90'])->assertSessionHasErrors('measured_on');

        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_the_database_refuses_an_impossible_weight(): void
    {
        $this->expectException(QueryException::class);

        Measurement::factory()->create(['weight_kg' => 5]);
    }

    public function test_the_chart_appears_with_two_or_more_measurements_and_marks_the_target(): void
    {
        $client = $this->client();
        WeightPlan::factory()->create(['user_id' => $client->id, 'target_weight_kg' => 80]);
        Measurement::factory()->create(['user_id' => $client->id, 'measured_on' => today()->subDays(7), 'weight_kg' => 95]);
        Measurement::factory()->create(['user_id' => $client->id, 'measured_on' => today(), 'weight_kg' => 92]);

        $this->actingAs($client)->get(route('account.progress.index'))
            ->assertOk()
            ->assertSee('<polyline', false)
            ->assertSee('role="img"', false)
            ->assertSee('الهدف 80');
    }

    public function test_a_single_measurement_shows_an_invitation_instead_of_a_chart(): void
    {
        $client = $this->client();
        Measurement::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->get(route('account.progress.index'))
            ->assertDontSee('<polyline', false)
            ->assertSee('سجّل قياسين على الأقل ليظهر مخطط تقدمك.');
    }

    public function test_the_history_table_gives_the_same_numbers_as_the_chart(): void
    {
        $client = $this->client();
        Measurement::factory()->create(['user_id' => $client->id, 'weight_kg' => 93.3, 'measured_on' => today()]);

        $this->actingAs($client)->get(route('account.progress.index'))->assertSee('93.3')->assertSee('سجل القياسات');
    }

    public function test_a_client_can_delete_their_own_entry_but_not_one_recorded_by_a_clinician(): void
    {
        $client = $this->client();
        $own = Measurement::factory()->create(['user_id' => $client->id, 'source' => 'client']);
        $clinical = Measurement::factory()->create(['user_id' => $client->id, 'source' => 'practitioner']);

        $this->actingAs($client)->delete(route('account.progress.destroy', $clinical))->assertForbidden();
        $this->actingAs($client)->delete(route('account.progress.destroy', $own))->assertRedirect();

        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($clinical);
    }

    public function test_measurements_are_soft_deleted_so_history_can_be_recovered(): void
    {
        $measurement = Measurement::factory()->create();

        $measurement->delete();

        $this->assertDatabaseHas('measurements', ['id' => $measurement->id]);
        $this->assertNotNull(Measurement::withTrashed()->find($measurement->id)->deleted_at);
    }

    public function test_the_history_is_paginated(): void
    {
        $client = $this->client();
        foreach (range(1, 18) as $i) {
            Measurement::factory()->create(['user_id' => $client->id, 'measured_on' => today()->subDays($i)]);
        }

        $response = $this->actingAs($client)->get(route('account.progress.index'))->assertOk();

        $this->assertSame(15, $response->viewData('measurements')->count());
        $this->assertSame(18, $response->viewData('measurements')->total());
    }
}
