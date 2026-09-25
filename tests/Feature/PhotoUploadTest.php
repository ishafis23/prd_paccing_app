<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable middleware for testing
        $this->withoutMiddleware();

        // Create roles
        Role::create(['name' => RoleName::Teknisi->value, 'guard_name' => 'web']);
        Role::create(['name' => RoleName::Admin->value, 'guard_name' => 'web']);

        // Create teknisi user
        $this->teknisi = User::factory()->withRole('teknisi')->create();

        // Create order assigned to teknisi
        $this->order = Order::factory()->create();
        $this->order->update([
            'teknisi_id' => $this->teknisi->id,
            'status' => 'dikerjakan',
        ]);
    }

    /** @test */
    public function photo_upload_stores_to_order_photos()
    {
        $this->actingAs($this->teknisi);

        $file = UploadedFile::fake()->image('test.jpg', 1200, 900);

        // Upload lokasi photo
        $response = $this->postJson('/teknisi/order/' . $this->order->id . '/photo', [
            'type' => 'lokasi',
            'unit_number' => 1,
            'photo_position' => 'lokasi',
            'file' => $file,
        ]);


        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['id', 'order_id', 'type', 'file_path']]);

        // Verify photo stored in database
        $this->assertDatabaseHas('order_photos', [
            'order_id' => $this->order->id,
            'type' => 'lokasi',
            'photo_position' => 'lokasi',
        ]);
    }

    /** @test */
    public function photo_upload_validates_file_size()
    {
        $this->actingAs($this->teknisi);

        // Create large fake file (>5MB)
        $file = UploadedFile::fake()->image('large.jpg')->size(6000);

        $response = $this->postJson('/teknisi/order/' . $this->order->id . '/photo', [
            'type' => 'lokasi',
            'unit_number' => 1,
            'photo_position' => 'lokasi',
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function photo_upload_validates_file_type()
    {
        $this->actingAs($this->teknisi);

        // Create text file instead of image
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $response = $this->postJson('/teknisi/order/' . $this->order->id . '/photo', [
            'type' => 'lokasi',
            'unit_number' => 1,
            'photo_position' => 'lokasi',
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function photo_upload_multiple_units_cuci()
    {
        $this->actingAs($this->teknisi);

        // Upload photos for Unit 1
        for ($i = 0; $i < 5; $i++) {
            $file = UploadedFile::fake()->image("cuci_unit1_$i.jpg");
            $positions = ['indoor', 'outdoor', 'area_indoor', 'area_outdoor', 'suhu'];

            $this->postJson('/teknisi/order/' . $this->order->id . '/photo', [
                'type' => 'cuci',
                'unit_number' => 1,
                'photo_position' => $positions[$i],
                'file' => $file,
            ])->assertStatus(201);
        }

        // Upload photos for Unit 2
        for ($i = 0; $i < 5; $i++) {
            $file = UploadedFile::fake()->image("cuci_unit2_$i.jpg");
            $positions = ['indoor', 'outdoor', 'area_indoor', 'area_outdoor', 'suhu'];

            $this->postJson('/teknisi/order/' . $this->order->id . '/photo', [
                'type' => 'cuci',
                'unit_number' => 2,
                'photo_position' => $positions[$i],
                'file' => $file,
            ])->assertStatus(201);
        }

        // Verify 10 photos stored (5 per unit)
        $this->assertEquals(10, OrderPhoto::where([
            'order_id' => $this->order->id,
            'type' => 'cuci',
        ])->count());

        // Verify both units present
        $this->assertEquals(1, OrderPhoto::where([
            'order_id' => $this->order->id,
            'type' => 'cuci',
            'unit_number' => 1,
        ])->count() / 5);

        $this->assertEquals(1, OrderPhoto::where([
            'order_id' => $this->order->id,
            'type' => 'cuci',
            'unit_number' => 2,
        ])->count() / 5);
    }

    /** @test */
    public function photo_retrieval_organized_by_type()
    {
        $this->actingAs($this->teknisi);

        // Upload verschiedene photos
        $files = [
            ['type' => 'lokasi', 'unit' => 1, 'pos' => 'lokasi'],
            ['type' => 'cuci', 'unit' => 1, 'pos' => 'indoor'],
            ['type' => 'service', 'unit' => 1, 'pos' => 'kendala'],
        ];

        foreach ($files as $photo) {
            $file = UploadedFile::fake()->image('test.jpg');
            $this->postJson('/teknisi/order/' . $this->order->id . '/photo', [
                'type' => $photo['type'],
                'unit_number' => $photo['unit'],
                'photo_position' => $photo['pos'],
                'file' => $file,
            ]);
        }

        // Retrieve all photos
        $response = $this->getJson('/teknisi/order/' . $this->order->id . '/photos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['lokasi', 'cuci', 'service', 'summary'],
            ]);

        // Verify summary
        $this->assertTrue($response->json('data.summary.total_photos_required') > 0);
        $this->assertEquals(3, $response->json('data.summary.total_photos_uploaded'));
    }

    /** @test */
    public function unauthorized_user_cannot_upload_photos()
    {
        $other_user = User::factory()->withRole('teknisi')->create();
        $this->actingAs($other_user);

        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson('/teknisi/order/' . $this->order->id . '/photo', [
            'type' => 'lokasi',
            'unit_number' => 1,
            'photo_position' => 'lokasi',
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function photo_deletion_only_deletes_own_photos()
    {
        // Upload photo with teknisi1
        $file = UploadedFile::fake()->image('test.jpg');
        $this->actingAs($this->teknisi);

        $response = $this->postJson('/teknisi/order/' . $this->order->id . '/photo', [
            'type' => 'lokasi',
            'unit_number' => 1,
            'photo_position' => 'lokasi',
            'file' => $file,
        ]);

        $photoId = $response->json('data.id');

        // Try to delete with different user
        $other_user = User::factory()->withRole('teknisi')->create();
        $this->actingAs($other_user);

        $deleteResponse = $this->deleteJson("/teknisi/photo/$photoId");

        $deleteResponse->assertStatus(403);

        // Verify photo still exists
        $this->assertDatabaseHas('order_photos', ['id' => $photoId]);
    }
}
