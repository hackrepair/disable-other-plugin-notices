# Security reporting verification — October 26, 2026

## Completed

- GitHub private vulnerability reporting was enabled for this repository and a subsequent read-only API check returned `enabled: true`.
- SECURITY.md uses the same reporting, supported-version, disclosure and testing framework as the other five THRG plugin repositories, with this plugin's own scope and limitations.
- README links to SECURITY.md, which gives the repository-specific private form and Jim Walker's fallback email address.
- Local policy checks passed. The identical verifier is included in all six repositories.
- Failure checks confirmed the verifier exits nonzero for a wrong repository URL, wrong fallback address or missing README policy link.
- Temporary ZIP builds passed and exclude the GitHub policy, verification procedure and developer script. Published ZIPs, versions, tags, SVN and installed plugins were not modified.

## Remaining evidence boundaries

The signed-in browser check could not reach the first GitHub report form because Ego Lite returned `ERR_SSL_PROTOCOL_ERROR`. No browser form is claimed verified for any repository. The API result verifies the setting, not every reporter's access. No dummy advisory or report was submitted.

The fallback address is the existing maintainer contact; no email was sent and inbox delivery/monitoring was not tested. Response targets are documented aims, not proven response performance or guaranteed resolution times. No application-security audit or production test was performed.

## Repeat

Run `python3 scripts/verify-security-policy.py` after pushing to verify the published default-branch policy, README link and live feature setting. Complete the signed-in browser and separately authorized mailbox checks described in `security-reporting-verification.md` when available. Record failures as failures; do not equate a document check with vulnerability assessment or inbox delivery.


## Post-push result

The full read-only verifier passed against GitHub after policy commit `8081e9e7f39b5875070a431905963d5b0fdf7ff3` was pushed: the default-branch SECURITY.md matches this checkout, the published README links to it, and private vulnerability reporting returns enabled. Browser form and inbox-delivery limitations above remain unresolved; no report or email was submitted.


## Browser follow-up completed — October 26, 2026

This supersedes the earlier browser-blocked result above. The signed-in maintainer browser opened this repository's `/security/advisories/new` route successfully. The correct repository name, blank title and summary fields, and private draft-advisory visibility information were confirmed. The intermittent SSL error on Disable Other Plugin Notices cleared after one normal reload; no TLS checks were disabled.

GitHub showed the **maintainer draft-advisory form**, not an independent outside reporter's view. This verifies maintainer access to the route and form only; outside-reporter access remains untested. Nothing was filled in or submitted, and no advisory/report was created. Fallback mailbox delivery and response performance remain untested. No plugin version, installed plugin, release or SVN content was changed.
