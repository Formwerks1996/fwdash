<?php

use App\Livewire\BusinessDevelopment\OutreachIndex;
use App\Models\OutreachProspect;
use App\Models\User;
use Livewire\Livewire;

function outreachFile(): string
{
    $folder = sys_get_temp_dir().'/outreach-'.uniqid();
    mkdir("{$folder}/outreach-thumbs", recursive: true);
    file_put_contents("{$folder}/outreach-thumbs/abc.jpg", 'jpeg-bytes');

    $row = fn (string $key, string $name, int $score, string $tier, string $status, array $extra = []) => [
        'key' => $key, 'name' => $name, 'score' => $score, 'tier' => $tier, 'region' => 'Metro Vancouver', 'hq' => 'Burnaby',
        'person' => null, 'activity' => ['status' => $status, 'confirmed' => true], 'types' => ['Townhomes'], 'tags' => [],
        'sources' => ['https://example.com'], 'recent' => [], 'projects' => [], 'img' => '', 'hasContact' => false,
        'hasLinkedIn' => false, 'hasEmail' => false, ...$extra,
    ];

    file_put_contents("{$folder}/outreach.json", json_encode([
        'built' => '2026-10-06',
        'mapUrl' => null,
        'types' => [['label' => 'Townhomes', 'color' => '#b5562f']],
        'rows' => [
            $row('allaire', 'Allaire Living', 5, 'A', 'Active', ['img' => 'abc.jpg', 'tags' => ['In deck'], 'projects' => [['name' => 'Stone Ridge', 'where' => 'Port Moody', 'img' => 'abc.jpg', 'lat' => 49.2, 'lon' => -122.8]]]),
            $row('quietco', 'Quiet Co', 3, 'C', 'Quiet'),
            $row('skipped', 'Skipped Ltd', 4, 'B', 'Active', ['tags' => ['Skip']]),
        ],
    ]));

    return "{$folder}/outreach.json";
}

it('imports the outreach research with its thumbnails', function () {
    $this->artisan('outreach:import', ['path' => outreachFile()])->assertSuccessful();

    expect(OutreachProspect::count())->toBe(3);
    $this->assertDatabaseHas('outreach_projects', ['prospect_key' => 'allaire', 'name' => 'Stone Ridge']);
    $this->assertDatabaseHas('outreach_images', ['name' => 'abc.jpg']);
});

it('filters to tiers A and B by default and hides skipped rows', function () {
    $this->artisan('outreach:import', ['path' => outreachFile()]);

    $this->actingAs(User::factory()->manager()->create());

    $component = Livewire::test(OutreachIndex::class);

    expect($component->get('rows')->pluck('key')->all())->toBe(['allaire']);

    $component->call('toggle', 'tiers', 'C');

    expect($component->get('rows')->pluck('key')->all())->toBe(['allaire', 'quietco']);
});

it('shows a prospect and serves its image', function () {
    $this->artisan('outreach:import', ['path' => outreachFile()]);

    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('outreach.show', 'allaire'))->assertOk()->assertSee('Stone Ridge');
    $this->get(route('outreach.images', 'abc.jpg'))->assertOk()->assertHeader('Cache-Control');
});
