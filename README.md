# lighthouse-error-handler

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/jBernavaPrah/lighthouse-error-handler/Tests?label=tests)](https://github.com/jBernavaPrah/lighthouse-error-handler/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Coverage Status](https://coveralls.io/repos/github/jBernavaPrah/lighthouse-error-handler/badge.svg?branch=main)](https://coveralls.io/github/jBernavaPrah/lighthouse-error-handler?branch=main)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/jbernavaprah/lighthouse-error-handler?style=flat-square)](https://packagist.org/packages/jBernavaPrah/lighthouse-error-handler)
[![Total Downloads](https://img.shields.io/packagist/dt/jBernavaPrah/lighthouse-error-handler.svg?style=flat-square)](https://packagist.org/packages/jBernavaPrah/lighthouse-error-handler)

### Is not PRODUCTION READY.
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


