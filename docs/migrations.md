# Schema changes and migrations

Navigation: [Docs index](./README.md) | [Propel workflow](./propel-workflow.md) | [Operations](./operations.md)

`schema.xml` is the source of truth of a module. Generating (or regenerating) the module compares the database with
it and writes the difference as a declarative **Phinx** migration in `Config/Migrations`: PHP calls on Phinx's table
API (`addColumn`, `changeColumn`, `addIndex`, `addForeignKey`, `changePrimaryKey`...), never SQL. `psfs:migrate` runs
them and `psfs:migrate:rollback --target=<version>` reverts them.

Propel is only used to read the database and to compute the difference. Phinx is the only engine that writes and runs
migrations: the fallback to the legacy Propel engine is off (`migrations.legacy_fallback_enabled=false`), so a missing
Phinx is an error and not a silent change of engine.

## What the generator guarantees

The migrations create what Propel's own SQL would create (`Config/Sql/<MODULE>.sql`), which is what the model classes
expect: `ENUM` is a `TINYINT` holding the position of the value (and its default is that position), `BOOLEAN` is
`TINYINT(1)`, `SMALLINT` and `TINYINT` keep their size, `LONGVARCHAR` becomes the `TEXT` type that fits its size, and
an index over part of a column keeps its length (`<index-column name="LABEL" size="20"/>`).

Statements are ordered so that MySQL accepts them, in both directions (`up` and `down`):

1. foreign keys that are dropped or changed;
2. new tables (without foreign keys) and the data copy into their translation tables;
3. column, index and primary key changes of the existing tables: a foreign key that relies on the primary key gets its
   own index first; a column that becomes `AUTO_INCREMENT` does it once the new key exists, and one that leaves stops
   being `AUTO_INCREMENT` before the key changes; a column that stops being part of the key becomes nullable after;
4. new and changed foreign keys, once every key they reference exists;
5. the copy of data back out of translation tables, and then the tables that are dropped.

The i18n behavior moves columns between `<table>` and `<table>_i18n`. The migration copies their values (in the
default locale of the translation table) in both directions instead of dropping them.

## Switches

| Key | Default | Effect |
|---|---|---|
| `migrations.engine` | `phinx` | Engine used by `psfs:migrate`; `--engine=propel` runs the legacy one for a single call |
| `migrations.legacy_fallback_enabled` | `false` | Use Propel when Phinx is not available (it is a Composer dependency, so this should never happen) |
| `migrations.remove_tables` | `false` | Drop the tables of the module that `schema.xml` no longer has. Only tables that start with the table prefix of the schema are touched, so tables of other modules in the same database are never dropped |
| `generator.migration.skip_unchanged` | `false` | Skip the database comparison when neither the schema nor the migrations changed since a comparison that found nothing (see [Operations](./operations.md), section 4.7) |

## Limits to know

- A renamed column is a dropped column plus a new one: Propel cannot tell, and its data is lost. Rename it by hand in a
  migration and keep the new name in `schema.xml`.
- A renamed table is reported as ambiguous when `migrations.remove_tables` is on, and the generation stops; with it off,
  the new table is created and the old one is kept.
- Changing the primary key of a table that other tables reference is not reordered: drop and recreate those foreign
  keys in a manual migration.
- A text column with a size is compared as the same `TEXT` MySQL reports (sizes inside the same `TEXT` type are not a
  change).

## How it is tested

`tests_api/ApiPhaseDSchemaEvolutionTest.php` takes a module through real schema changes against MySQL
(`tests/examples/generator/Evolution`: columns, indexes, foreign keys, primary keys, composite keys, defaults, tables
and behaviors, and a chain of all of them that ends in the first schema). After every step the database must equal
the one Propel's SQL creates for the new schema (an oracle that does not depend on the generator), regenerating must
find nothing left to migrate, the data must survive, and rolling back every migration must restore the original
structure.
