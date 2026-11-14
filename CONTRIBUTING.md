# Contributing

PR review enforces the rules in this document. They exist to keep abilities
**canonical** (one implementation per ability), **core-landing-ready** (no
consumer fingerprints in the code), and **safe to consume** (promises that
match the tier system in [NAME-LIFECYCLE.md](NAME-LIFECYCLE.md)).

## Review rules

### Coding standards

- Code follows the **WordPress core coding standards** (enforced by WPCS via
  `composer run lint:phpcs`).
- The text domain is **`default`** for every translatable string in an
  ability (labels, descriptions).
- Static analysis must pass `composer run lint:phpstan` (PHPStan +
  phpstan-wordpress).

### Abilities are written core-landing-ready

Abilities are self-contained classes with closures for callbacks, no plugin
helpers, no AI plumbing, no runtime dependencies — nothing that would need to
be unwound when the ability lands in core. Landing in core (and backporting
from it) should be a mechanical transform, not a port. A PR that bakes a
consumer's quirks into an ability's logic will not be merged; consumer
differences belong in the consumer.

### Canonical schema single-source

Consumers may never fork an ability's schema, and neither may this repo drift
for a consumer's sake: **a PR that changes a canonical ability's schema to
work around a consumer's (e.g. AI client model) incompatibility is rejected.**
Fix the AI client, or add a consumer-side adapter — never change the
canonical ability.

### Every `core/` ability links a ticket

Ability names are adopted with a paper trail: a `core/`-prefix or `core`-tier
ability must link a core trac/GitHub ticket, recorded in its manifest entry's
`proposal` field. Experimental (`wp/`) abilities don't need one yet; filing
it is part of promotion. Promotions and renames follow the promotion path and
rename protocol in [NAME-LIFECYCLE.md](NAME-LIFECYCLE.md).

### Per-ability test requirements

Every ability PR ships tests that:

- register through the `Registrar` and assert input/output schemas;
- cover **permission negative and positive on every gate path** — the
  request-level capability floor and any row/status-level checks — including
  fail-closed behavior on malformed or unknown input;
- test `execute` against a mocked data provider.

Write abilities additionally document their annotations (`readonly`,
`idempotent`, `destructive`) and their `required_capabilities` floor.

### Renames: no aliases, reviewers reject alias PRs

Renames follow the rename protocol: the new name enters the manifest in the
same release the old name leaves it, the entry records `renamed_from`, and
the CHANGELOG notes the break. PRs that add an alias, shim, or parallel
registration under the old name are **rejected** — no exceptions, ever.

## What CI checks

- `phpcs` — core standards, text domain `default`.
- `phpstan` — with phpstan-wordpress.
- `verify:manifest` (the `bin/verify-manifest.php` gate) + `ManifestTest` —
  the §8.3 manifest rules from both runtimes: tier/prefix rules, name
  uniqueness, rename lineage (`renamed_from` collides with no live name and
  never renames to itself), class existence (`extends
  Abstract_Core_Ability`), category single-sourcing, and version sync
  (`Manifest::VERSION` is the single source of truth — composer.json
  deliberately carries no version field — and must match the newest git tag
  when tags exist, else the `0.1.0` seed).
- `verify:coreify` — the coreify round-trip gate (`tools/coreify.php
  --check`), for every tier-core ability with a transform profile
  (`core/content-query`, `core/settings-get`, `core/users-query`): reverse
  of the trunk fixture followed by forward must reproduce the original core
  file, and the reverse output must match the hand-port
  (`src/Content/Content_Query.php`, `src/Settings/Settings_Get.php`,
  `src/Users/Users_Query.php`), modulo whitespace and comments. A new
  tier-core backport adds its profile to `PROFILES` in `tools/coreify.php`.
- PHPUnit on both environments — WordPress trunk and latest stable. Both
  ship the Abilities API in core (the 6.9+ support floor; stable pre-7.2
  carries the real pre-7.2 API), so core-skip and backport paths are both
  exercised against core-provided APIs on each environment.

## Local workflow

```bash
composer install
composer run lint:phpcs
composer run lint:phpstan
composer run verify:manifest
composer run verify:coreify
composer test
```

## Merging PRs

All members of the [abilities-maintainers team](https://github.com/orgs/WordPress/teams/abilities-maintainers) within the WordPress org on GitHub have the ability to review and merge PRs.  If you are not a maintainer but have reviewed a PR and are confident in the code, approve the PR and comment pinging @wordpress/abilities-maintainers or a specific maintainer who has been involved in the PR.  Once a maintainer confirms there are no objections, you are free to merge the PR into `main`.

Most PRs will be automatically assigned a release milestone, but please make sure your merged PR was assigned one.  Doing so creates the historical legacy of what code landed when, and makes it possible for all project contributors (even non-technical ones) to access this information.

## Joining the abilities-maintainers team

If you have 2-3 meaningful accepted contributions, ask in the [#core-ai Slack channel](https://wordpress.slack.com/archives/C08TJ8BPULS) to get added to a specific section of the CODEOWNERS file so you're auto-assigned for PR reviews.

If you have 3-5 meaningful PR reviews after being added to CODEOWNERS, ask in the [#core-ai Slack channel](https://wordpress.slack.com/archives/C08TJ8BPULS) to get added to the abilities-maintainers team.

## Closing PRs

Sometimes, a PR may not be mergeable, no matter how much additional effort is applied to it (e.g. out of scope).  In these cases, it’s best to communicate with the contributor graciously while describing why the PR was closed, this encourages productive future involvement.

Make sure to:

1. Thank the contributor for their time and effort.
2. Fully explain the reasoning behind the decision to close the PR.
3. Link to as much supporting documentation as possible.

If you’d like a template to follow:

> Thanks \_\_\_\_ for the time you’ve spent on this PR.
>
> I’m closing this PR because \_\_\_\_. To clarify further, \_\_\_\_.
>
> For more details, please see \_\_\_\_ and \_\_\_\_.
