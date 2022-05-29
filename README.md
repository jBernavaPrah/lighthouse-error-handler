# lighthouse-error-handler
Implement error handler like described here: https://blog.logrocket.com/handling-graphql-errors-like-a-champ-with-unions-and-interfaces/

It's still work in progress. So not production ready!


## Requirements

- [laravel/laravel:^9.0](https://github.com/laravel/laravel)
- [nuwave/lighthouse:^5.5](https://github.com/nuwave/lighthouse)

## Installation

#### 1. Install using composer:

```bash
composer require jbernavaprah/lighthouse-error-handler
```

#### 2. Publish configuration and schema

```bash
php artisan vendor:publish --tag=lighthouse-error-handler
```

## That's it!
You will se every type new type covered by the default errors from Laravel/Lumen.


