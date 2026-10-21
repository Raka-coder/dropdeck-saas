<?php

use App\Support\Position;

it('spaces a new list by the gap', function () {
    expect(Position::between(null, null))->toBe(Position::GAP);
});

it('returns the next position after the maximum', function () {
    expect(Position::next(1024))->toBe(2048);
    expect(Position::next(null))->toBe(Position::GAP);
});

it('returns the midpoint between two neighbours', function () {
    expect(Position::between(1024, 2048))->toBe(1536);
});

it('halves the first position when inserting at the top', function () {
    expect(Position::between(null, 1024))->toBe(512);
});

it('returns null when there is no integer room left', function () {
    expect(Position::between(10, 11))->toBeNull();
    expect(Position::between(null, 1))->toBeNull();
});

it('returns previous plus gap when appending', function () {
    expect(Position::between(1024, null))->toBe(2048);
});

it('builds an evenly spaced sequence', function () {
    expect(Position::sequence(3))->toBe([1024, 2048, 3072]);
});
