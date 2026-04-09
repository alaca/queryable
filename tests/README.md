# Tests

## Unit Tests

```bash
composer run test
```

## Integration Tests (WordPress)

Requires MySQL and a one-time setup:

```bash
bash bin/install-wp-tests.sh queryable_test root '' localhost latest
```

Arguments: `<db-name> <db-user> <db-pass> <db-host> <wp-version>`

Then run:

```bash
composer run test:wp
```
