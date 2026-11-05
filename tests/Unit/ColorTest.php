<?php

use App\Support\Color;

it('normalizes hex values', function (?string $in, ?string $out) {
    expect(Color::normalize($in))->toBe($out);
})->with([
    ['#abc', '#AABBCC'],
    ['ff0000', '#FF0000'],
    [' #00Ae42 ', '#00AE42'],
    ['zzz', null],
    [null, null],
]);

it('buckets colors into families', function (string $hex, string $family) {
    expect(Color::family($hex))->toBe($family);
})->with([
    ['#000000', 'black'],
    ['#FFFFFF', 'white'],
    ['#8E9089', 'grey'],
    ['#C12E1F', 'red'],
    ['#FF6A13', 'orange'],
    ['#F4EE2A', 'yellow'],
    ['#00AE42', 'green'],
    ['#0A2989', 'blue'],
    ['#5E43B7', 'purple'],
    ['#F55A74', 'pink'],
    ['#7D6556', 'brown'],
]);

it('picks readable label text', function () {
    expect(Color::contrastText('#000000'))->toBe('#FFFFFF')
        ->and(Color::contrastText('#FFFFFF'))->toBe('#000000')
        ->and(Color::contrastText('#F4EE2A'))->toBe('#000000')
        ->and(Color::contrastText('#0A2989'))->toBe('#FFFFFF');
});
