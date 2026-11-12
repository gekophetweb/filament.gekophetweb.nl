/**
 * Estimates the filament and its cost for an STL picked in the print request form, in the browser,
 * before anything is uploaded. Mirrors App\Support\StlMeasurement and App\Support\FilamentEstimate;
 * the numbers it needs (fill share, density, spool price) come from PHP, so both give the same result.
 *
 * Usage: x-data="stlEstimate({ fillLow, fillHigh, density, material, spoolPrice, spoolGrams })"
 * on an element holding the file input, with x-on:change="estimate($event.target.files[0])".
 */

/**
 * Triangles of a binary or ASCII STL as flat lists of nine coordinates.
 *
 * @param {ArrayBuffer} buffer
 * @returns {Iterable<number[]>}
 */
function* triangles(buffer) {
    const view = new DataView(buffer);
    const count = buffer.byteLength >= 84 ? view.getUint32(80, true) : -1;

    if (count >= 0 && buffer.byteLength === 84 + 50 * count) {
        for (let offset = 84; offset + 50 <= buffer.byteLength; offset += 50) {
            const triangle = [];
            // Skip the 12-byte normal: three vertices of three little-endian floats follow.
            for (let i = 0; i < 9; i++) {
                triangle.push(view.getFloat32(offset + 12 + i * 4, true));
            }
            yield triangle;
        }

        return;
    }

    const text = new TextDecoder().decode(buffer);
    const vertex = /^\s*vertex\s+(\S+)\s+(\S+)\s+(\S+)/gim;
    let triangle = [];

    for (const match of text.matchAll(vertex)) {
        triangle.push(parseFloat(match[1]), parseFloat(match[2]), parseFloat(match[3]));
        if (triangle.length === 9) {
            yield triangle;
            triangle = [];
        }
    }
}

/**
 * Volume (mm³, by the divergence theorem) and bounding box (mm) of the mesh, or null without triangles.
 *
 * @param {ArrayBuffer} buffer
 * @returns {{ volume: number, dimensions: number[] } | null}
 */
export function measureStl(buffer) {
    let sixfoldVolume = 0;
    let count = 0;
    const min = [Infinity, Infinity, Infinity];
    const max = [-Infinity, -Infinity, -Infinity];

    for (const [ax, ay, az, bx, by, bz, cx, cy, cz] of triangles(buffer)) {
        sixfoldVolume += ax * (by * cz - bz * cy) - ay * (bx * cz - bz * cx) + az * (bx * cy - by * cx);
        count++;

        for (const [axis, values] of [[0, [ax, bx, cx]], [1, [ay, by, cy]], [2, [az, bz, cz]]]) {
            min[axis] = Math.min(min[axis], ...values);
            max[axis] = Math.max(max[axis], ...values);
        }
    }

    if (count === 0 || !Number.isFinite(sixfoldVolume)) {
        return null;
    }

    return { volume: Math.abs(sixfoldVolume) / 6, dimensions: max.map((value, axis) => value - min[axis]) };
}

/** Whole grams, at least 1; above 100 g to the nearest 5 g, like FilamentEstimate::round(). */
function roundGrams(grams) {
    return Math.max(1, grams > 100 ? Math.round(grams / 5) * 5 : Math.round(grams));
}

function range(low, high) {
    return low === high ? low : `${low}–${high}`;
}

function formatSize(size) {
    return String(parseFloat(size.toFixed(1)));
}

export function stlEstimate(config) {
    return {
        /** @type {{ grams: string, price: string, size: string, misScaled: boolean } | null} */
        result: null,
        busy: false,
        failed: false,

        async estimate(file) {
            this.result = null;
            this.failed = false;

            if (!file) {
                return;
            }

            this.busy = true;

            try {
                const measured = measureStl(await file.arrayBuffer());

                if (!measured) {
                    this.failed = true;

                    return;
                }

                const solidGrams = (measured.volume / 1000) * config.density;
                const low = roundGrams(solidGrams * config.fillLow);
                const high = Math.max(low, roundGrams(solidGrams * config.fillHigh));
                const price = (grams) => Math.max(0.01, (grams * config.spoolPrice) / config.spoolGrams).toFixed(2);
                const largest = Math.max(...measured.dimensions);

                this.result = {
                    grams: `≈ ${range(low, high)} g of ${config.material}`,
                    price: `≈ €${range(price(low), price(high))}`,
                    size: `${measured.dimensions.map(formatSize).join(' × ')} mm`,
                    misScaled: largest < 3 || largest > 1000,
                };
            } catch {
                this.failed = true;
            } finally {
                this.busy = false;
            }
        },
    };
}
