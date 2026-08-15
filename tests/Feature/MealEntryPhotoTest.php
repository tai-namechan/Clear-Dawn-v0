<?php

namespace Tests\Feature;

use App\Enums\MealType;
use App\Models\FoodLookupRequest;
use App\Models\MealEntry;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MealEntryPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MatrixRowSeeder::class);
        Storage::fake('food-label-ocr');
        config(['meals.label_ocr.disk' => 'food-label-ocr']);
    }

    public function test_confirm_photo_estimate_persists_image_on_meal_entry(): void
    {
        $user = User::factory()->create();
        $sourcePath = 'food-photo-estimate/'.$user->id.'/shot.jpg';
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create([
            'source' => 'ai_photo_estimate',
            'barcode' => null,
            'barcode_type' => null,
            'temp_image_path' => $sourcePath,
        ]);
        Storage::disk('food-label-ocr')->put($sourcePath, 'fake-jpeg-bytes');

        $response = $this->actingAs($user)->postJson(
            route('meals.barcode-lookup.confirm', $lookup->id),
            $this->confirmPayload(),
        );

        $response->assertCreated()
            ->assertJsonPath('entry.has_photo', true)
            ->assertJsonPath('created', true);

        $entry = MealEntry::query()->where('user_id', $user->id)->sole();
        $this->assertNotNull($entry->photo_path);
        $this->assertSame(
            'meal-photos/'.$user->id.'/'.$entry->id.'.jpg',
            $entry->photo_path,
        );
        Storage::disk('food-label-ocr')->assertExists($entry->photo_path);
        Storage::disk('food-label-ocr')->assertMissing($sourcePath);
        $this->assertSame('fake-jpeg-bytes', Storage::disk('food-label-ocr')->get($entry->photo_path));

        $lookup->refresh();
        $this->assertNull($lookup->temp_image_path);

        $this->assertSame(
            route('meals.photo', $entry),
            $response->json('entry.photo_url'),
        );
    }

    public function test_owner_can_preview_and_download_meal_photo(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryWithPhoto($user);

        $preview = $this->actingAs($user)
            ->get(route('meals.photo', $entry))
            ->assertOk();

        $this->assertSame('image/jpeg', $preview->headers->get('Content-Type'));
        $this->assertSame('fake-jpeg-bytes', $preview->streamedContent());

        $download = $this->actingAs($user)
            ->get(route('meals.photo', ['mealEntry' => $entry, 'download' => 1]))
            ->assertOk();

        $this->assertSame('fake-jpeg-bytes', $download->streamedContent());
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString($entry->id.'.jpg', (string) $download->headers->get('Content-Disposition'));
    }

    public function test_guest_cannot_view_meal_photo(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryWithPhoto($user);

        $this->get(route('meals.photo', $entry))
            ->assertRedirect(route('login'));
    }

    public function test_other_user_cannot_view_meal_photo(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $entry = $this->entryWithPhoto($owner);

        $this->actingAs($other)
            ->get(route('meals.photo', $entry))
            ->assertForbidden();
    }

    public function test_photo_endpoint_returns_not_found_without_image(): void
    {
        $user = User::factory()->create();
        $entry = MealEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('meals.photo', $entry))
            ->assertNotFound();
    }

    public function test_meals_index_exposes_photo_url_for_entries_with_photos(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryWithPhoto($user, ['eaten_on' => '2026-07-25']);

        $this->actingAs($user)
            ->get(route('meals.index', ['date' => '2026-07-25']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Meals/Index')
                ->where('sections', function ($sections) use ($entry): bool {
                    $found = collect($sections)
                        ->flatMap(fn (array $section): array => $section['entries'])
                        ->firstWhere('id', $entry->id);

                    return is_array($found)
                        && $found['has_photo'] === true
                        && $found['photo_url'] === route('meals.photo', $entry);
                })
            );
    }

    public function test_deleting_meal_entry_deletes_photo_file(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryWithPhoto($user);
        $path = (string) $entry->photo_path;

        $this->actingAs($user)
            ->deleteJson(route('meals.destroy', $entry))
            ->assertOk();

        $this->assertDatabaseMissing('meal_entries', ['id' => $entry->id]);
        Storage::disk('food-label-ocr')->assertMissing($path);
    }

    public function test_copying_meal_entry_copies_photo(): void
    {
        $user = User::factory()->create();
        $source = $this->entryWithPhoto($user, [
            'eaten_on' => '2026-07-20',
            'meal_type' => MealType::Breakfast,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('meals.copy', $source), [
                'eaten_on' => '2026-07-25',
                'meal_type' => MealType::Lunch->value,
            ])
            ->assertCreated()
            ->assertJsonPath('entry.has_photo', true);

        $copy = MealEntry::query()->whereKey($response->json('entry.id'))->firstOrFail();
        $this->assertNotSame($source->photo_path, $copy->photo_path);
        Storage::disk('food-label-ocr')->assertExists((string) $source->photo_path);
        Storage::disk('food-label-ocr')->assertExists((string) $copy->photo_path);
        $this->assertSame(
            'fake-jpeg-bytes',
            Storage::disk('food-label-ocr')->get((string) $copy->photo_path),
        );
    }

    public function test_copy_previous_day_copies_photos(): void
    {
        $user = User::factory()->create();
        $source = $this->entryWithPhoto($user, [
            'eaten_on' => '2026-07-24',
            'meal_type' => MealType::Dinner,
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.copy-previous-day'), ['date' => '2026-07-25'])
            ->assertOk()
            ->assertJsonPath('copied', 1);

        $copy = MealEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('eaten_on', '2026-07-25')
            ->sole();

        $this->assertNotNull($copy->photo_path);
        $this->assertNotSame($source->photo_path, $copy->photo_path);
        Storage::disk('food-label-ocr')->assertExists((string) $copy->photo_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmPayload(): array
    {
        return [
            'name' => 'チキンカレー',
            'serving_label' => '1皿',
            'kcal' => 680,
            'protein_g' => 25,
            'fat_g' => 28,
            'carb_g' => 80,
            'add_to_meal' => true,
            'eaten_on' => '2026-07-25',
            'meal_type' => MealType::Lunch->value,
            'quantity' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function entryWithPhoto(User $user, array $overrides = []): MealEntry
    {
        $entry = MealEntry::factory()->for($user)->create($overrides);
        $path = 'meal-photos/'.$user->id.'/'.$entry->id.'.jpg';
        Storage::disk('food-label-ocr')->put($path, 'fake-jpeg-bytes');
        $entry->forceFill(['photo_path' => $path])->save();

        return $entry->fresh();
    }
}
