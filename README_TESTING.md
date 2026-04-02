# Testing Guide for Moralis Tracker Package

This document explains how to run and understand the test suite for the Moralis Tracker Laravel package.

## Test Structure

The test suite is organized into:

- **Unit Tests** - Test individual components in isolation
  - `tests/Unit/Models/` - Model tests (TrackedAddress, ChainTransaction)
  - `tests/Unit/Services/` - Service layer tests (TransactionSyncService)
  - `tests/Unit/` - Client tests (MoralisClient)

- **Feature Tests** - Test complete workflows and integrations
  - `tests/Feature/SyncWorkflowTest.php` - End-to-end sync scenarios
  - `tests/Feature/CommandsTest.php` - Artisan command tests

## Running Tests

### Prerequisites

```bash
cd tracking-address
composer install
```

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Unit tests only
vendor/bin/phpunit tests/Unit

# Feature tests only
vendor/bin/phpunit tests/Feature

# Specific test file
vendor/bin/phpunit tests/Unit/Models/TrackedAddressTest.php

# Specific test method
vendor/bin/phpunit --filter test_can_create_tracked_address
```

### Run with Coverage (requires Xdebug)

```bash
vendor/bin/phpunit --coverage-html coverage
```

## Test Coverage

### Model Tests

**TrackedAddressTest** - Tests for the `TrackedAddress` model:
- ✅ Creating tracked addresses
- ✅ Marking addresses as synced
- ✅ Active scope filtering
- ✅ Getting native symbol by chain
- ✅ Chain configuration retrieval
- ✅ Meta field JSON casting
- ✅ Transactions relationship

**ChainTransactionTest** - Tests for the `ChainTransaction` model:
- ✅ Creating chain transactions
- ✅ Type filtering (normal, token, nft)
- ✅ Chain filtering
- ✅ Address filtering
- ✅ Incoming/outgoing transaction scopes
- ✅ Raw data JSON casting
- ✅ Combined scope queries

### Service Tests

**TransactionSyncServiceTest** - Tests for the sync service:
- ✅ Syncing all active addresses
- ✅ Filtering by chain
- ✅ Creating transactions from API data
- ✅ Updating last synced block
- ✅ Preventing duplicate transactions (upsert)
- ✅ Wei to native currency conversion
- ✅ Handling token transfers
- ✅ Error handling

### Client Tests

**MoralisClientTest** - Tests for the API client:
- ✅ Client initialization
- ✅ Chain resolution
- ✅ Method existence checks

### Feature Tests

**SyncWorkflowTest** - End-to-end sync scenarios:
- ✅ Complete sync workflow (normal + token transactions)
- ✅ Multi-chain sync (same address on different chains)
- ✅ Incremental sync from last block
- ✅ Failed transaction handling
- ✅ Complex query filtering

**CommandsTest** - Artisan command tests:
- ✅ Adding tracked addresses
- ✅ Custom chain specification
- ✅ Preventing duplicates
- ✅ Address normalization (lowercase)

## Key Testing Patterns

### 1. Mocking the MoralisClient

Since we don't want to make real API calls during tests, we mock the client:

```php
$mockClient = Mockery::mock(MoralisClient::class);
$mockClient->shouldReceive('getNormalTransactions')->andReturn([...]);
```

### 2. Database Assertions

```php
$this->assertDatabaseHas('tracked_addresses', [
    'address' => '0xtest',
    'chain'   => 'bsc',
]);
```

### 3. Testing Scopes

```php
$results = ChainTransaction::forAddress('0xwallet')
    ->onChain('bsc')
    ->ofType('token')
    ->get();
```

### 4. Testing Relationships

```php
$address = TrackedAddress::create([...]);
$this->assertCount(1, $address->transactions);
```

## What the Tests Verify

### ✅ Core Functionality
- Address tracking across multiple chains
- Transaction syncing (normal, token, NFT)
- Incremental sync from last block
- Upsert strategy (no duplicates)

### ✅ Data Integrity
- Proper wei to native conversion
- Lowercase address normalization
- JSON field casting
- Timestamp handling

### ✅ Query Capabilities
- Filtering by chain, type, address
- Incoming/outgoing transaction queries
- Combined scope queries
- Relationship queries

### ✅ Edge Cases
- Failed transactions (receipt_status = 0)
- Empty API responses
- Duplicate transaction handling
- Multi-chain same address

### ✅ Commands
- Adding addresses via CLI
- Chain specification
- Duplicate prevention

## Continuous Integration

To run tests in CI/CD:

```yaml
# Example GitHub Actions
- name: Run Tests
  run: |
    cd tracking-address
    composer install
    vendor/bin/phpunit
```

## Troubleshooting

### "Class not found" errors
```bash
composer dump-autoload
```

### Database errors
Tests use SQLite in-memory database. Ensure `php-sqlite3` is installed:
```bash
php -m | grep sqlite
```

### Mockery errors
Ensure you call `Mockery::close()` in `tearDown()`:
```php
protected function tearDown(): void
{
    Mockery::close();
    parent::tearDown();
}
```

## Adding New Tests

When adding features, follow this pattern:

1. **Write the test first** (TDD approach)
2. **Use descriptive test names**: `test_feature_does_something_specific`
3. **Mock external dependencies** (API calls, etc.)
4. **Assert expected behavior** clearly
5. **Clean up in tearDown()** if needed

Example:
```php
public function test_sync_handles_rate_limiting()
{
    // Arrange
    $mockClient = Mockery::mock(MoralisClient::class);
    $mockClient->shouldReceive('getNormalTransactions')
        ->andThrow(new \Exception('Rate limit exceeded'));
    
    // Act
    $service = new TransactionSyncService($mockClient);
    $result = $service->syncAll();
    
    // Assert
    $this->assertEquals(1, $result['errors']);
}
```

## Test Database

Tests use an in-memory SQLite database that is:
- Created fresh for each test
- Automatically migrated
- Destroyed after each test

No cleanup needed between tests!
