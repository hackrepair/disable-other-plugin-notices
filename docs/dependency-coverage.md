# Repository protection and dependency coverage

## Verify

With an authenticated GitHub CLI account that can inspect repository settings, run:

```bash
python3 scripts/verify-repository-protection.py
```

This read-only check requires secret scanning, push protection and Dependabot alerts to be enabled, and requires GitHub's dependency graph to contain every package/version or Actions ref detected by `dependency-inventory.py`. An empty graph cannot pass when dependencies are expected. API failures and missing entries fail the check. Allow indexing to finish after submission before checking again.

To inspect the local inventory without contacting GitHub:

```bash
python3 scripts/dependency-inventory.py --output /tmp/thrg-dependencies.json
```

The Dependency inventory workflow submits snapshots on pushes to main or a manual dispatch. It does not run for pull requests. The checkout action is pinned to a verified commit and does not persist credentials. Its job-scoped contents-write permission is required by GitHub for dependency submission; it is not an external app grant or a credential stored in the repository.

## Coverage

- Where bundled, Plugin Update Checker is identified from its source header and Parsedown from its declared version. Both use verified Composer package identities so GitHub can match advisory records. These version declarations can identify an upstream baseline even if the bundled copy is modified; advisory findings require source review rather than automatic dismissal.
- The modified, unversioned `PucReadmeParser.php`, where present, is tracked as a manual-review item with its SHA-256. No package identity or version is invented for it.
- External GitHub Actions refs are inventoried from committed workflow YAML. Existing floating version tags are declared refs, not immutable artifact identities. The new inventory workflow itself pins its checkout action.
- Plugin Archiver does not bundle this updater. Its inventory covers its workflow dependencies; this does not imply zero security risk in its PHP code.

## Limits and maintenance

This is an inventory of identified bundled libraries and Actions refs, not a complete resolved dependency lock or a security audit. WordPress and PHP on client sites, transitive dependencies of Actions, and development tools downloaded by commands such as `npx @wordpress/env@10` are not fully resolved here. No fake Composer/npm manifest is added, and no library is automatically upgraded or merged. Updating a bundled library requires the existing review, tests and release process.

The workflow reads current source declarations on each run and fails if an expected bundled parser version cannot be read. Review the inventory rules when adding/removing third-party code, renaming vendor files or changing build tooling. Do not interpret zero alerts as a clean bill of security. The published install ZIPs exclude this workflow, scripts and documentation. No plugin release or WordPress.org SVN change is made by enabling repository protections.
