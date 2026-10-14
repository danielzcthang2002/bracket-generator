# Bracket module

Tournament logic lives under the `App\Bracket` namespace:

All module classes, interfaces, enums, and traits have a `Dt` prefix (for
example, `DtTournament`, `DtTournamentService`, and `DtHasMode`). The module's
tables are `dt_tournaments`, `dt_players`, `dt_tournament_matches`,
`dt_match_scores`, and `dt_tournament_match_participants`. Column names and
HTTP routes remain unchanged.

- `Models`: tournaments, players, matches, participants, and scores.
- `Services`: tournament and player operations, match scoring, and bracket modes.
- `Contracts`: mode interfaces and the mode resolver.
- `Traits`: shared group, participant, and dependent-match logic.
- `Enums`: tournament modes, statuses, match states, and formats.
- `database/migrations`: the five tournament table migrations.
- `Providers`: the service provider that registers the module's migrations.

Laravel's existing `App\` Composer autoload mapping loads this module. The
container can resolve its services without additional bindings:

```php
use App\Bracket\Services\DtTournamentService;

$service = app(DtTournamentService::class);
$tournament = $service->getTournamentById($openId);
```

Controllers and HTTP resources remain in `app/Http`; they adapt the module to
this application's API and Inertia pages. Tournament integration tests remain
in `tests/Feature/Tournament`.

`App\Bracket\Providers\DtBracketServiceProvider` is registered in
`bootstrap/providers.php` and loads the migrations from this module. Run
`php artisan migrate` normally; no custom migration path is needed for a fresh
installation. Migration filenames use `create_dt_*_table.php` and retain their
original timestamps and execution order.
Application infrastructure migrations remain in `database/migrations`.

For databases created before the `dt_` prefix, rename the five existing
bracket tables to the names above before deploying this code. For databases
that have already run these migrations, also update the corresponding five
`migration` values in the `migrations` table from `*_create_*_table` to
`*_create_dt_*_table`, retaining their timestamps and batch numbers. This
applies even if the database already has prefixed tables but recorded the old
filenames. Laravel identifies migrations by filename; without this update,
it will attempt to run the renamed creation migrations again. Renaming the
migration files does not rename existing tables. Preserve the data, column names,
and foreign keys during that database upgrade; do not use `migrate:fresh`
on a database containing data you need to keep. No automatic rename migration
is included because a consuming application may own unrelated unprefixed
tables with the same names.

This directory is a module within the current application, not yet an
installable Composer package. Extracting it into a package requires a package
namespace and Composer manifest, provider auto-discovery, and a declared
Laravel/PHP support range. Consumer applications
can supply their own controllers, routes, authentication, and presentation.
