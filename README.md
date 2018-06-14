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

Run the command

```sh
bin/console app:harvest:export
```
