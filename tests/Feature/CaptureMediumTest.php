<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureMediumTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_medium_detects_channel(): void
    {
        $form = LeadForm::factory()->create(['display_mode' => 'inline']);
        $widget = LeadForm::factory()->create(['display_mode' => 'floating']);
        $user = User::factory()->create();

        $this->assertSame('form', Lead::factory()->create(['lead_form_id' => $form->id])->captureMedium()['key']);
        $this->assertSame('widget', Lead::factory()->create(['lead_form_id' => $widget->id])->captureMedium()['key']);
        $this->assertSame('manual', Lead::factory()->create(['captured_by' => $user->id, 'lead_form_id' => null])->captureMedium()['key']);
        $this->assertSame('other', Lead::factory()->create(['captured_by' => null, 'lead_form_id' => null])->captureMedium()['key']);
    }
}
