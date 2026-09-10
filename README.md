# Karnoweb CRM

Laravel domain package for CRM identity, interactions, pipelines, segments, and campaigns.

The package stores data, computes projections, and publishes events. It does not send SMS, send email, create orders, or call host-domain services.

See the [documentation index](docs/README.md).

## Requirements

- PHP 8.3+
- Laravel 13.x

## Installation

```bash
composer require karnoweb/crm:^13.0
php artisan vendor:publish --tag=crm-config
php artisan migrate
```

Dependency-injected services are the primary API. The `Crm` facade is convenience only.
