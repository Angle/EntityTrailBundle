# anglemx/trail-bundle

Symfony bundle that automatically records **who changed what data and when**, for any
Doctrine-managed entity. Drop-in replacement for per-entity changelog tables
(`DimensionChangeLog`, `AgreementFeeChangeLog`, …). Provides opt-out attributes,
configurable field exclusions, and an optional admin UI.

Every create / update / delete of a tracked entity writes one row to a single
`trail_logs` table with a JSON diff (`{"field": {"old": x, "new": y}}`), the acting
user, their IP, and a timestamp.

## Requirements

| | |
|-|-|
| PHP | `>= 8.2` |
| Symfony | `^6.4 \| ^7.0` |
| Doctrine ORM | `^3.0` |

`symfony/security-bundle` and `symfony/twig-bundle` are required because the default
user provider resolves the acting user from the security context, and the admin UI
renders Twig. If you override `user_provider` **and** set `enable_admin: false`, neither
is used at runtime — but they remain Composer dependencies.

## How it works

`TrailListener` subscribes to Doctrine lifecycle events. It captures the changeset
during `preUpdate` / `postPersist` / `preRemove`, queues an audit row in memory, and
writes the queued rows in `postFlush` with a **raw DBAL insert** — no ORM involvement in
the write, so there's no recursive flush cycle and no risk of the audit write joining the
business transaction. An `onFlush` reset guarantees a rolled-back transaction can't leak
audit rows into the next successful flush.

The `trail_logs` table stores `entity_type` / `entity_id` as plain columns — there are no
ORM relations to your entities, so the bundle stays decoupled from every project.

## Installation

```bash
composer require anglemx/trail-bundle
```

### 1. Register the bundle

```php
// config/bundles.php
return [
    // ...
    Angle\TrailBundle\AngleTrailBundle::class => ['all' => true],
];
```

### 2. Configure it

```yaml
# config/packages/angle_trail.yaml
angle_trail:
    track_creates: true
    track_updates: true
    track_deletes: true
    exclude_fields: [updatedAt, createdAt, code]   # excluded from ALL entities
    exclude_entities:
        - App\Entity\JobSchedulerLog
        - App\Entity\NotificationEmailLog
    enable_admin: true
    admin_route_prefix: /admin/trail-log
    user_provider: null   # null = default SecurityTrailUserProvider; or a service id
```

All keys are optional; the defaults above (minus the example exclusions) are applied
automatically. Note Symfony **replaces** array defaults — if you set `exclude_fields`,
your list fully overrides the built-in `[updatedAt, createdAt, deletedAt]`.

### 3. Import the admin routes (only if `enable_admin: true`)

```yaml
# config/routes/audit.yaml
_angle_trail:
    resource: '@AngleTrailBundle/config/routes.yaml'
    prefix: /admin/trail-log
```

### 4. Create the `trail_logs` table

The bundle ships a migration, but a project's `doctrine_migrations.migrations_paths`
does not scan `vendor/` by default. Pick one:

**Option A — point Doctrine at the bundle's migration:**

```yaml
# config/packages/doctrine_migrations.yaml
doctrine_migrations:
    migrations_paths:
        'DoctrineMigrations': '%kernel.project_dir%/migrations'
        'Angle\TrailBundle\Migrations': '%kernel.project_dir%/vendor/anglemx/trail-bundle/migrations'
```

```bash
bin/console doctrine:migrations:migrate
```

**Option B — generate it yourself.** The `TrailLog` entity is auto-mapped by the bundle,
so Doctrine already knows the schema:

```bash
bin/console make:migration   # generates the CREATE TABLE trail_logs migration
bin/console doctrine:migrations:migrate
```

That's the entire install. No entity changes, no listener to register — everything is
automatic from here.

## Opting out

### Skip an entire entity

```php
use Angle\TrailBundle\Attribute\TrailExclude;

#[TrailExclude]
class SessionLog { /* ... */ }
```

### Skip sensitive / high-churn fields

```php
use Angle\TrailBundle\Attribute\TrailIgnore;

class User
{
    #[TrailIgnore]
    private string $passwordHash;

    #[TrailIgnore]
    private ?\DateTimeImmutable $lastLoginAt;   // changes on every login

    #[TrailIgnore]
    private ?string $rememberMeToken;
}
```

### Skip vendor entities you can't annotate

```yaml
angle_trail:
    exclude_entities:
        - Angle\JobSchedulerBundle\Entity\JobSchedulerLog
```

## Querying the trail

Inject `Angle\TrailBundle\Repository\TrailLogRepository`:

```php
$repo->findByEntity(Client::class, $clientId);        // full history, newest first
$repo->findLastUpdate(Client::class, $clientId);      // "last edited by" UI
$repo->countByUser($userId, $from, $to);              // activity in a date range
```

## Custom user provider

The default `SecurityTrailUserProvider` reads the logged-in user and request IP, and
returns `null` for everything in CLI/worker contexts. To customise (e.g. a worker that
acts on behalf of a user), implement the contract and point the config at your service:

```php
use Angle\TrailBundle\Contract\TrailUserProviderInterface;

final class MyTrailUserProvider implements TrailUserProviderInterface
{
    public function getCurrentUserId(): ?int { /* ... */ }
    public function getCurrentUserLabel(): ?string { /* ... */ }
    public function getCurrentIpAddress(): ?string { /* ... */ }
}
```

```yaml
angle_trail:
    user_provider: App\Audit\MyTrailUserProvider
```

## Admin UI

When `enable_admin: true`, three routes are registered under your prefix:

| Route | Path | Purpose |
|-------|------|---------|
| `angle_trail_list` | `GET {prefix}/` | DataTables list with entity-type and action filters |
| `angle_trail_data` | `POST {prefix}/data` | DataTables server-side data endpoint |
| `angle_trail_view` | `GET {prefix}/{code}` | Full changeset for one entry |

Templates extend `@AngleTrail/base.html.twig` (a minimal Bootstrap 5 + DataTables base).
Override it in your project at `templates/bundles/AngleTrailBundle/base.html.twig` to drop
the list/view pages into your own admin layout.

## Testing

```bash
composer install
vendor/bin/phpunit
```

The suite ships unit tests (configuration, entity, attributes, user provider, DataTables
controller) and a functional test that boots a real Symfony kernel with an in-memory
SQLite database and asserts that create/update/delete actually write `trail_logs` rows,
that `#[TrailExclude]` / `#[TrailIgnore]` / `exclude_fields` are honoured, and that the
acting user is recorded.

## Non-goals

- Does **not** replace git history or schema migrations (those track code, not data).
- Does **not** audit raw SQL run outside Doctrine.
- Does **not** provide row-level restore/rollback — it's a read-only audit trail.
- Does **not** send notifications on changes.
