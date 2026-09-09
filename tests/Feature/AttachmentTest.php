<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(StatusSeeder::class);
    }

    private function vendor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('vendedor');

        return $user;
    }

    public function test_vendor_can_upload_an_attachment(): void
    {
        Storage::fake('local');
        $vendor = $this->vendor();
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);

        Volt::actingAs($vendor)
            ->test('leads.show', ['lead' => $lead])
            ->set('upload', UploadedFile::fake()->create('propuesta.pdf', 200, 'application/pdf'))
            ->call('addAttachment')
            ->assertHasNoErrors();

        $attachment = $lead->attachments()->first();

        $this->assertNotNull($attachment);
        $this->assertSame('propuesta.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('timeline_events', ['lead_id' => $lead->id, 'type' => 'attachment']);
    }

    public function test_upload_rejects_disallowed_type(): void
    {
        Storage::fake('local');
        $vendor = $this->vendor();
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);

        Volt::actingAs($vendor)
            ->test('leads.show', ['lead' => $lead])
            ->set('upload', UploadedFile::fake()->create('malware.exe', 10))
            ->call('addAttachment')
            ->assertHasErrors('upload');

        $this->assertSame(0, $lead->attachments()->count());
    }

    public function test_vendor_can_delete_attachment(): void
    {
        Storage::fake('local');
        $vendor = $this->vendor();
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);
        $path = UploadedFile::fake()->create('x.pdf', 10)->store('attachments/'.$lead->id, 'local');
        $attachment = $lead->attachments()->create([
            'path' => $path,
            'original_name' => 'x.pdf',
            'uploaded_by' => $vendor->id,
        ]);

        Volt::actingAs($vendor)
            ->test('leads.show', ['lead' => $lead])
            ->call('deleteAttachment', $attachment->id);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_assigned_vendor_can_download_but_others_cannot(): void
    {
        Storage::fake('local');
        $vendor = $this->vendor();
        $other = $this->vendor();
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);
        $path = UploadedFile::fake()->create('x.pdf', 10)->store('attachments/'.$lead->id, 'local');
        $attachment = $lead->attachments()->create([
            'path' => $path,
            'original_name' => 'x.pdf',
            'uploaded_by' => $vendor->id,
        ]);

        $this->actingAs($vendor)
            ->get(route('leads.attachments.download', [$lead, $attachment]))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('leads.attachments.download', [$lead, $attachment]))
            ->assertForbidden();
    }
}
