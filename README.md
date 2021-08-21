# Test Data

Seeds test data in Flamarkt.

Optionally integrates with the following extensions if they are enabled:

- Flamarkt Identity
- Flamarkt Balance
- Flamarkt Categories
- Flamarkt Taxonomies
- Flamarkt Library
- Flamarkt Product Slugs
- Flamarkt Final Quantities

## Syntax

    php flarum flamarkt:seed
        --reset
        --user-count=100
        --category-count=50
        --taxonomy-count=10
        --product-count=50
        --order-count=100
        --min-product-categories=0
        --max-product-categories=3
        --min-product-taxonomies=0
        --max-product-taxonomies=10
        --min-product-terms=1
        --max-product-terms=3

`--reset` will truncate all Flamarkt related tables including `users`.

The `min`/`max` options for Terms apply for each of the Taxonomies selected through `min`/`max` option for Taxonomies.

All parameters are optional.
The default values are shown in the command.
Use `0` as `count` or `max` to disable seeding a particular item.

## Extensibility

Events are available to extend the seeding process. See `src/Events`.

## Compatibility with Fake Data

This extension can be used alongside [Fake Data](https://github.com/migratetoflarum/fake-data) without any issue.
But the two extensions don't integrate with each other.

Both extensions provide user seeds.
If you have already seeded users with Fake data, consider using `--user-count=0` in this extension to prevent conflicts with unique email addresses.

Users seeded by Fake Data won't have Flamarkt Identity/Balance seeded.
But users seeded by Fake Data can be picked as order owners.
