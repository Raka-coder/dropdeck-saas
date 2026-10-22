<?php

use App\Livewire\Dashboard\RecentLinks;
use App\Models\DropLink;
use App\Models\User;
use Livewire\Livewire;

it('lists only the owner latest links', function () {
    $user = User::factory()->create();

    $links = collect(range(1, 6))->map(fn (int $index) => DropLink::factory()->create([
        'user_id' => $user->id,
        'label' => 'Link '.$index,
    ]));

    $foreign = DropLink::factory()->create(['label' => 'Link asing']);

    $this->actingAs($user);

    $visible = Livewire::test(RecentLinks::class)
        ->assertSee('Link 6')
        ->assertSee('Link 2')
        ->assertDontSee('Link 1')
        ->assertDontSee('Link asing')
        ->instance()
        ->links;

    expect($visible)->toHaveCount(5)
        ->and($visible->pluck('id')->all())->not->toContain($links->first()->id)
        ->and($visible->pluck('id')->all())->toContain($links->last()->id)
        ->and($visible->pluck('id')->all())->not->toContain($foreign->id);
});

it('shows the empty state without links', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(RecentLinks::class)
        ->assertSee(__('dashboard.links.empty'));
});
