# Opusfakturering

## Installation

```sh
composer install
```

Defined your database settings (using environment variables) and run

```sh
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate --no-interaction
```


### Harvest stuff

1. Go to https://id.getharvest.com/developers and “Create New Personal
   Access Token”.
2. Define the environment variable `HARVEST_ACCOUNT` with the value of
   “Account ID”.
3. Define the environment variable `HARVEST_TOKEN` with the value of
   “Your Token”.

## Updating

```sh
composer install
bin/console doctrine:migrations:migrate --no-interaction
```


## Processing invoices

Create a new account in the database, e.g.

```sh
bin/console itk-dev:database:cli <<< 'insert into account(name, configuration) values ("ITK (grafisk service)", "{\"type\": \"harvest\"}");'
```

`@TODO`: It doesn't really make sense to have a general “harvest” account in this project – the content and processing is unique to “ITK (grafisk service)”.

Run the command

```sh
bin/console app:harvest:export -vv
```
