<?php

namespace Tests\Feature;

use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Enums\FoodLookupStatus;
use App\Jobs\LookupFoodLabelOcrJob;
use App\Models\FoodLookupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoodLabelImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('food-label-ocr');
        config(['meals.label_ocr.disk' => 'food-label-ocr']);
    }

    private function validImage(): UploadedFile
    {
        return UploadedFile::fake()->image('label.jpg', 800, 600);
    }

    public function test_guests_cannot_upload_label_images(): void
    {
        $this->postJson(route('meals.label-ocr.store'), ['image' => $this->validImage()])
            ->assertUnauthorized();
    }

    public function test_attach_to_not_found_lookup_starts_ocr(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->notFound()->create();

        Queue::fake();

        $response = $this->actingAs($user)->postJson(
            route('meals.barcode-lookup.label-image.store', $lookup->id),
            ['image' => $this->validImage()],
        );

        $response->assertStatus(202)->assertJson([
            'status' => 'ocr_pending',
            'lookup_id' => $lookup->id,
        ]);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::OcrPending, $lookup->status);
        $this->assertNotNull($lookup->temp_image_path);
        Storage::disk('food-label-ocr')->assertExists($lookup->temp_image_path);

        Queue::assertPushed(LookupFoodLabelOcrJob::class, fn (LookupFoodLabelOcrJob $job) => $job->lookupRequestId === $lookup->id);
    }

    public function test_attach_to_failed_lookup_replaces_previous_image(): void
    {
        $user = User::factory()->create();
        Storage::disk('food-label-ocr')->put('food-label-ocr/old.jpg', 'old');
        $lookup = FoodLookupRequest::factory()->for($user)->create([
            'status' => FoodLookupStatus::Failed,
            'error_code' => 'ocr_unreadable',
            'temp_image_path' => 'food-label-ocr/old.jpg',
        ]);

        Queue::fake();

        $this->actingAs($user)->postJson(
            route('meals.barcode-lookup.label-image.store', $lookup->id),
            ['image' => $this->validImage()],
        )->assertStatus(202);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::OcrPending, $lookup->status);
        $this->assertNull($lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing('food-label-ocr/old.jpg');
        Storage::disk('food-label-ocr')->assertExists($lookup->temp_image_path);
    }

    public function test_attach_scoped_to_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($other)->notFound()->create();

        $this->actingAs($user)->postJson(
            route('meals.barcode-lookup.label-image.store', $lookup->id),
            ['image' => $this->validImage()],
        )->assertNotFound();
    }

    public function test_attach_rejects_lookup_in_wrong_status(): void
    {
        $user = User::factory()->create();
        $pending = FoodLookupRequest::factory()->for($user)->create([
            'status' => FoodLookupStatus::Pending,
        ]);
        $found = FoodLookupRequest::factory()->for($user)->found()->create();

        foreach ([$pending, $found] as $lookup) {
            $this->actingAs($user)->postJson(
                route('meals.barcode-lookup.label-image.store', $lookup->id),
                ['image' => $this->validImage()],
            )->assertNotFound();
        }
    }

    public function test_standalone_upload_creates_barcodeless_lookup(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $response = $this->actingAs($user)->postJson(
            route('meals.label-ocr.store'),
            ['image' => $this->validImage()],
        );

        $response->assertStatus(202)->assertJson(['status' => 'ocr_pending']);

        $lookup = FoodLookupRequest::query()->sole();
        $this->assertSame($user->id, $lookup->user_id);
        $this->assertNull($lookup->barcode);
        $this->assertNull($lookup->barcode_type);
        $this->assertSame(FoodLookupStatus::OcrPending, $lookup->status);
        Storage::disk('food-label-ocr')->assertExists($lookup->temp_image_path);

        Queue::assertPushed(LookupFoodLabelOcrJob::class, fn (LookupFoodLabelOcrJob $job) => $job->lookupRequestId === $lookup->id);
    }

    public function test_upload_rejects_invalid_files(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $cases = [
            'not an image' => UploadedFile::fake()->create('label.pdf', 100, 'application/pdf'),
            'unsupported mime' => UploadedFile::fake()->image('label.gif', 800, 600),
            'too large' => UploadedFile::fake()->image('label.jpg', 800, 600)->size(6000),
            'too small dimensions' => UploadedFile::fake()->image('label.jpg', 100, 100),
        ];

        foreach ($cases as $file) {
            $this->actingAs($user)->postJson(
                route('meals.label-ocr.store'),
                ['image' => $file],
            )->assertUnprocessable()->assertJsonValidationErrors('image');
        }

        $this->assertSame(0, FoodLookupRequest::query()->count());
        $this->assertSame([], Storage::disk('food-label-ocr')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_upload_rejected_when_monthly_quota_exhausted(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);

        $user = User::factory()->create();
        app(AiUsageLedger::class)->reserve(
            $user->id,
            'fill',
            'claude-haiku-4-5-20251001',
            AiMoney::of('0.000001'),
        );

        Queue::fake();

        $this->actingAs($user)->postJson(
            route('meals.label-ocr.store'),
            ['image' => $this->validImage()],
        )->assertUnprocessable()->assertJsonPath('message', fn (string $m) => str_contains($m, 'AI利用枠'));

        $this->assertSame(0, FoodLookupRequest::query()->count());
        $this->assertSame([], Storage::disk('food-label-ocr')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_sequential_double_upload_returns_not_found(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->notFound()->create();

        Queue::fake();

        $this->actingAs($user)->postJson(
            route('meals.barcode-lookup.label-image.store', $lookup->id),
            ['image' => $this->validImage()],
        )->assertStatus(202);

        // 1回目で ocr_pending へ遷移済みなのでスコープクエリに掛からない
        $this->actingAs($user)->postJson(
            route('meals.barcode-lookup.label-image.store', $lookup->id),
            ['image' => $this->validImage()],
        )->assertNotFound();

        Queue::assertPushed(LookupFoodLabelOcrJob::class, 1);
    }
}
