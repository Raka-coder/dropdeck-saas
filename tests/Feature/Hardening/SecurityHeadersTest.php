<?php

use App\Models\User;

it('sends baseline security headers on public responses', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy-Report-Only');
});

it('sends baseline security headers on authenticated pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('reports the CSP in report-only mode without blocking inline styles', function () {
    $header = $this->get(route('home'))->headers->get('Content-Security-Policy-Report-Only');

    expect($header)
        ->toContain("default-src 'self'")
        ->toContain("style-src 'self' 'unsafe-inline'")
        ->toContain("frame-ancestors 'none'");
});
