# Opusfakturering

## Installation

```sh
composer install
```

### Harvest stuff

1. Go to https://id.getharvest.com/developers and “Create New Personal
   Access Token”.
2. Define the environment variable `HARVEST_ACCOUNT` with the value of
   “Account ID”.
3. Define the environment variable `HARVEST_TOKEN` with the value of
   “Your Token”.


## Process invoices

Run the command

```sh
bin/console app:harvest:export
```
