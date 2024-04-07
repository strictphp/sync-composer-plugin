#!/usr/bin/env bash

cd "$(dirname "$0")"

set -e

# Define paths to the necessary files
INIT_JSON="composer-init.json"
COMPOSER_JSON="composer.json"
EXPECTED_JSON="composer-expected.json"

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Ensure the initial composer.json is in place
cp $INIT_JSON $COMPOSER_JSON

echo "Running update-scripts command..."
echo "START --"

composer update-scripts -vvvv

echo "END --"
echo " "

# Compare the resulting composer.json to the expected output
if cmp -s "$COMPOSER_JSON" "$EXPECTED_JSON"; then
    echo -e "${GREEN}SUCCESS:${NC} composer.json matches the expected output."
else
    # Add red color to the output
    echo -e "${RED}FAILURE:${NC} composer.json does not match the expected output."

    diff $COMPOSER_JSON $EXPECTED_JSON
    exit 1
fi
