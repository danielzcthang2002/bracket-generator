<?php

use App\Models\Tournament;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('paginates tournaments and retains filters in pagination links', function () {
    for ($index = 1; $index <= 16; $index++) {
        Tournament::query()->create([
            'name' => 'Cup '.$index,
            'open_id' => 'CUP'.$index,
            'mode_type' => 'swiss',
            'status' => 'open',
        ]);
    }

    $this->get('/tournaments?search=Cup&mode=swiss&status=open')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tournament/tournament-index')
            ->has('tournaments.data', 15)
            ->where('tournaments.total', 16)
            ->where('tournaments.next_page_url', fn ($url) => str_contains($url, 'search=Cup') && str_contains($url, 'mode=swiss') && str_contains($url, 'status=open')));

    $this->get('/tournaments?search=Cup&mode=swiss&status=open&page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->has('tournaments.data', 1)
            ->where('tournaments.current_page', 2)
            ->where('tournaments.data.0.open_id', 'CUP1'));
});

it('searches names and open IDs while applying both filters', function (string $search) {
    foreach ([
        ['Summer Cup', 'MATCH01', 'swiss', 'open'],
        ['Summer Cup', 'MATCH02', 'round_robin', 'open'],
        ['Summer Cup', 'MATCH03', 'swiss', 'ended'],
        ['Winter Cup', 'OTHER01', 'swiss', 'open'],
    ] as [$name, $openId, $mode, $status]) {
        Tournament::query()->create(['name' => $name, 'open_id' => $openId, 'mode_type' => $mode, 'status' => $status]);
    }

    $this->get('/tournaments?'.http_build_query(['search' => $search, 'mode' => 'swiss', 'status' => 'open']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournaments.total', 1)
            ->where('tournaments.data.0.open_id', 'MATCH01'));
})->with(['Summer', 'MATCH']);

it('returns an empty page for searches without matches', function () {
    $this->get('/tournaments?search=missing')
        ->assertInertia(fn (Assert $page) => $page->has('tournaments.data', 0)->where('tournaments.total', 0));
});
