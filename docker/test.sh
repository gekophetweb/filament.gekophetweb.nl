#!/bin/sh
# Run the test suite in a throwaway PHP container: ./docker/test.sh [pest args]
exec env MSYS_NO_PATHCONV=1 docker run --rm -v "$(pwd -W 2>/dev/null || pwd):/work" -w /work composer:2 vendor/bin/pest --colors=never "$@"
