# Competition Parser Laravel
[![build](https://github.com/rubenvanerk/competition-parser-lumen/workflows/build/badge.svg)](https://github.com/rubenvanerk/competition-parser-lumen/actions?query=workflow%3Abuild)

This is a continuation of the [competition-parser](https://github.com/rubenvanerk/competition-parser) project with a user-friendly frontend.

## Installation

```bash
cp .env.example .env
```

Edit `.env` to match your environment. 

```bash
composer install  
php artisan migrate
php artisan db:seed
npm install
npm run dev
php artisan serve
```

To compile assets use `npm run dev` or use `npm run watch` to automatically rebuild assets whenever you change a file.

`npm run watch` also spawns a browsersync that automatically refreshes after a change.

## API docs
To generate the API docs, run `php artisan l5-swagger:generate`. To view them go to /api/documentation.
