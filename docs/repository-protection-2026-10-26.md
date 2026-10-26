# Repository protections — October 26, 2026

User authorized secret scanning, push protection and establishing verified dependency coverage across the six THRG plugin repositories.

## Settings

GitHub REST API readback confirms secret scanning and repository push protection enabled. Dependabot alerts are enabled. Enabling alerts also enables the dependency graph; Site Inspector's live settings page confirms the graph is on. Per-repository snapshot acceptance and graph verification are recorded after the workflow runs. No dummy credentials were pushed and no secrets are reproduced in this document.

## Inventory implementation

The new trusted-main-only Dependency inventory workflow submits declared bundled library versions and external Actions refs. It uses a commit-pinned checkout action, no external Python dependencies and a job-scoped token with the contents-write permission required by GitHub's submission API. No fork/PR event is configured and no new credential is stored. Existing workflow refs are inventoried as declared, not silently upgraded. `verify-repository-protection.py` checks the live feature flags, Dependabot alerts and actual indexed names/versions; `dependency-inventory.py --output PATH` is local/read-only, while `--submit` writes dependency metadata to GitHub.

Local fixture checks pass for version detection, runtime parent/child relationships, Actions refs, unversioned parser hashing and refusal to inventory an unknown parser version. Runtime plugin source, installed plugins, existing ZIPs, release tags and WordPress.org SVN are unchanged. Existing Plugin Detective screenshot edits are preserved separately.

## Coverage limits and review item

Where bundled, Plugin Update Checker declares 5.7 and Parsedown declares 1.6.0. GitHub's public advisory query lists **GHSA-qgpv-86r3-87fh** (medium, XSS) and **GHSA-62m3-fc7f-jpp8** (high, class-name injection) as affecting upstream Parsedown 1.6.0. The bundled file may be modified; these are upstream-baseline findings, not a demonstrated exploit in this plugin. Do not auto-dismiss them or silently upgrade the library; review source provenance and the affected readme-rendering path separately.

Archiver does not bundle that updater or parser. Its inventory is limited to identified workflow dependencies. All inventories exclude unresolved transitive Actions dependencies, actual WordPress/PHP on client sites and unlocked development CLI tooling. The unversioned modified readme parser is a manual-review item. See `dependency-coverage.md` for repeat commands and boundaries.

## Live results

Pending workflow submission and read-only dependency-graph verification.
