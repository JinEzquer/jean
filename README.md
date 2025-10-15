# PatricksColdCut (Symfony 7)

## Quick Start

- **Requirements**
  - PHP 8.2+
  - Composer
  - Node.js 18+
  - MySQL/MariaDB (XAMPP) or SQLite

- **Install dependencies**
```bash
composer install
npm install
```

- **Configure environment**
1. Copy `.env.local.example` to `.env.local`.
2. Set `APP_ENV=dev`.
3. Set `DATABASE_URL` (examples below).

MySQL example:
```
DATABASE_URL="mysql://username:password@127.0.0.1:3306/patricks_coldcut?serverVersion=8.0&charset=utf8mb4"
```
SQLite example:
```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

- **Database & assets**
```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n
npm run dev
```

- **Run the app**
```bash
php -S 127.0.0.1:8000 -t public
# or symfony local server if installed
```
Visit http://127.0.0.1:8000

## Demo Script (Rubric Presentation)
- Create a `Category` at `/category/new`.
- Create a `Product` at `/product/new` and assign the `Category`.
- Show product at `/product/{id}`.
- Edit and delete flows via `/product/{id}/edit` and list actions.
- Explain entity relationship: `Product` ManyToOne `Category`.
- Show routes autoloaded by attributes (`src/Controller/*`).

## Project Structure
- Entities: `src/Entity/{Product,Category,User,FrozenGoods}.php`
- Controllers: `src/Controller/{ProductController,CategoryController,...}.php`
- Templates: `templates/{product,category,base}.twig`
- Styles: `assets/styles/app.css` (custom responsive CSS)
- Migrations: `migrations/Version*.php`

## Notes
- Uses Webpack Encore (`package.json`) with `{{ encore_entry_*('app') }}` in `base.html.twig`.
- If you prefer SQLite for quick demo, set `DATABASE_URL` to SQLite as above and re-run migrations.
