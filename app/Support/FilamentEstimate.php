<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A rough estimate of the filament a print takes: the model's solid weight in the material, scaled to
 * what a typical slice uses (walls, top and bottom, and around 15% infill). Supports, brims and purge
 * waste are not in it. Good for "about how much", not for planning to the gram.
 */
final readonly class FilamentEstimate
{
    /** Share of the solid weight a typical print uses, low and high. */
    public const FILL_LOW = 0.3;

    public const FILL_HIGH = 0.5;

    /** Density (g/cm³) when the material is unknown: PLA, the most common one. */
    public const DEFAULT_DENSITY = 1.24;

    /**
     * Typical densities in g/cm³, by material name prefix. Longer prefixes come first, so "PETG" wins over "PET".
     */
    private const DENSITIES = [
        'PLA-CF' => 1.29, 'PETG-CF' => 1.30, 'PA6-CF' => 1.17, 'PAHT-CF' => 1.17, 'PA-CF' => 1.17, 'PET-CF' => 1.40,
        'PETG' => 1.27, 'PCTG' => 1.23, 'HIPS' => 1.04, 'PPS' => 1.30, 'PVA' => 1.23, 'PLA' => 1.24, 'ABS' => 1.04,
        'ASA' => 1.07, 'TPU' => 1.21, 'PET' => 1.38, 'PA' => 1.14, 'PC' => 1.20, 'PP' => 0.90,
    ];

    /**
     * @param  float  $solidGrams  The model's weight if it were printed solid.
     * @param  list<string>  $materials  Material names the estimate is for.
     */
    public function __construct(
        public float $solidGrams,
        public array $materials,
    ) {}

    /**
     * @param  float  $volume  Cubic millimetres.
     * @param  list<string>  $materials  Picked materials; their average density is used, PLA when none.
     */
    public static function forVolume(float $volume, array $materials): self
    {
        $materials = array_values(array_unique($materials));
        $densities = array_map(self::densityOf(...), $materials) ?: [self::DEFAULT_DENSITY];
        $density = array_sum($densities) / count($densities);

        return new self($volume / 1000 * $density, $materials);
    }

    public static function densityOf(string $material): float
    {
        $name = Str::upper(trim($material));

        foreach (self::DENSITIES as $prefix => $density) {
            if (str_starts_with($name, $prefix)) {
                return $density;
            }
        }

        return self::DEFAULT_DENSITY;
    }

    public function lowGrams(): int
    {
        return self::round($this->solidGrams * self::FILL_LOW);
    }

    public function highGrams(): int
    {
        return max($this->lowGrams(), self::round($this->solidGrams * self::FILL_HIGH));
    }

    /** Grams on one spool, for the price per gram. */
    public const SPOOL_GRAMS = 1000;

    /**
     * Filament cost of the estimate at config('access.print_request_spool_price') per 1 kg spool, for example "≈ €0.80–1.38".
     */
    public function priceLabel(): string
    {
        $price = fn (int $grams): string => number_format(max(0.01, $grams * (float) config('access.print_request_spool_price') / self::SPOOL_GRAMS), 2);
        $low = $price($this->lowGrams());
        $high = $price($this->highGrams());

        return '≈ €'.($low === $high ? $low : "{$low}–{$high}");
    }

    /** For example "≈ 35–60 g of PLA". */
    public function label(): string
    {
        $range = $this->lowGrams() === $this->highGrams() ? "{$this->lowGrams()}" : "{$this->lowGrams()}–{$this->highGrams()}";

        return "≈ {$range} g of ".($this->materials === [] ? 'PLA' : implode(' / ', $this->materials));
    }

    /** Whole grams, at least 1; above 100 g to the nearest 5 g, since the estimate is not finer than that. */
    private static function round(float $grams): int
    {
        return max(1, $grams > 100 ? (int) (round($grams / 5) * 5) : (int) round($grams));
    }
}
