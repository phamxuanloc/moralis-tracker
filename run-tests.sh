#!/bin/bash

# Navigate to the tracking-address directory
cd "$(dirname "$0")"

echo "Installing dependencies..."
composer install --no-interaction

echo ""
echo "Running PHPUnit tests..."
vendor/bin/phpunit

echo ""
echo "Tests completed!"
