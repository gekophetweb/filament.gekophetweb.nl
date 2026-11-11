<?php

namespace App\Support;

use Generator;

/**
 * The volume and bounding box of an STL mesh, read straight from the file (binary or ASCII STL).
 * STL has no unit; slicers read it as millimetres, and so does this.
 */
final readonly class StlMeasurement
{
    /** Triangles read per chunk of a binary STL. */
    private const CHUNK_TRIANGLES = 4096;

    /**
     * @param  float  $volume  Cubic millimetres.
     * @param  array{0: float, 1: float, 2: float}  $dimensions  Width, depth and height in millimetres.
     */
    public function __construct(
        public float $volume,
        public array $dimensions,
        public int $triangles,
    ) {}

    /**
     * Null when the file is not a readable STL with at least one triangle.
     */
    public static function fromFile(string $path): ?self
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $header = (string) fread($handle, 84);
            $count = strlen($header) === 84 ? unpack('V', $header, 80)[1] : -1;
            $isBinary = $count >= 0 && filesize($path) === 84 + 50 * $count;

            if (! $isBinary) {
                rewind($handle);
            }

            return self::measure($isBinary ? self::binaryTriangles($handle, $count) : self::asciiTriangles($handle));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Whether the size suggests the file was not exported in millimetres (inches or metres).
     *
     * @param  array{0: float, 1: float, 2: float}  $dimensions
     */
    public static function looksMisScaled(array $dimensions): bool
    {
        $largest = max($dimensions);

        return $largest < 3 || $largest > 1000;
    }

    /**
     * Signed volume by the divergence theorem (one tetrahedron from the origin per triangle) and the bounding box.
     *
     * @param  iterable<array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float, 7: float, 8: float}>  $triangles
     */
    private static function measure(iterable $triangles): ?self
    {
        $sixfoldVolume = 0.0;
        $count = 0;
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];

        foreach ($triangles as [$ax, $ay, $az, $bx, $by, $bz, $cx, $cy, $cz]) {
            // a · (b × c) is six times the signed volume of the tetrahedron origin, a, b, c.
            $sixfoldVolume += $ax * ($by * $cz - $bz * $cy) - $ay * ($bx * $cz - $bz * $cx) + $az * ($bx * $cy - $by * $cx);
            $count++;

            $min = [min($min[0], $ax, $bx, $cx), min($min[1], $ay, $by, $cy), min($min[2], $az, $bz, $cz)];
            $max = [max($max[0], $ax, $bx, $cx), max($max[1], $ay, $by, $cy), max($max[2], $az, $bz, $cz)];
        }

        if ($count === 0 || ! is_finite($sixfoldVolume)) {
            return null;
        }

        return new self(abs($sixfoldVolume) / 6, [$max[0] - $min[0], $max[1] - $min[1], $max[2] - $min[2]], $count);
    }

    /**
     * @param  resource  $handle
     * @return Generator<int, array<int, float>>
     */
    private static function binaryTriangles($handle, int $count): Generator
    {
        for ($done = 0; $done < $count; $done += self::CHUNK_TRIANGLES) {
            $chunk = (string) fread($handle, 50 * min(self::CHUNK_TRIANGLES, $count - $done));

            for ($offset = 0; $offset + 50 <= strlen($chunk); $offset += 50) {
                // Skip the 12-byte normal: three vertices of three little-endian floats follow.
                yield array_values(unpack('g9', $chunk, $offset + 12));
            }
        }
    }

    /**
     * @param  resource  $handle
     * @return Generator<int, array<int, float>>
     */
    private static function asciiTriangles($handle): Generator
    {
        $vertices = [];

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\s*vertex\s+(\S+)\s+(\S+)\s+(\S+)/i', $line, $match) !== 1) {
                continue;
            }

            array_push($vertices, (float) $match[1], (float) $match[2], (float) $match[3]);

            if (count($vertices) === 9) {
                yield $vertices;
                $vertices = [];
            }
        }
    }
}
