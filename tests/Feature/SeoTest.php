<?php

use App\Models\User;

it('renders complete SEO metadata on the landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>'.e(config('seo.title')).'</title>', false)
        ->assertSee('name="description" content="'.e(config('seo.description')).'"', false)
        ->assertSee('name="robots" content="index, follow"', false)
        ->assertSee('rel="canonical" href="'.route('home').'"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('property="og:image"', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('DropDeck');
});

it('marks private application pages as noindex', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex, nofollow"', false);
});

it('marks authentication pages as noindex with the brand title', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex, follow"', false)
        ->assertSee(e(__('Log in').' — '.config('app.name')));
});

it('serves an XML sitemap with the public landing page', function () {
    $this->get(route('seo.sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('home').'</loc>', false);
});

it('serves robots.txt with private paths and the sitemap URL', function () {
    $this->get(route('seo.robots'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /dashboard')
        ->assertSee('Sitemap: '.route('seo.sitemap'));
});
