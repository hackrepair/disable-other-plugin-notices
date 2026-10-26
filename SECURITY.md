# Security policy

This policy applies to **The Hack Repair Guy's Disable Other Plugin Notices**.

## Report a vulnerability privately

Please do not disclose vulnerabilities, exploit details or sensitive data in public issues, discussions, WordPress.org support forums or reviews.

Use [GitHub private vulnerability reporting](https://github.com/hackrepair/disable-other-plugin-notices/security/advisories/new). A free GitHub account is required. If that route is unavailable or you prefer email, contact **[Jim Walker at jim@hackrepair.com](mailto:jim@hackrepair.com)**. For email, use the subject **Security report: The Hack Repair Guy's Disable Other Plugin Notices**. Start with a minimal description; arrange a suitable private channel before sharing particularly sensitive material.

For general questions and non-security bugs, use the support links in the README.

## What to include

- Plugin version and relevant WordPress/PHP versions.
- Affected feature and the permissions or other conditions needed.
- Reproduction steps using a local or staging site, with a minimal proof of concept if available.
- Expected behavior, observed behavior and likely security impact.
- A suggested fix or mitigation, if you have one.

Remove passwords, reset links, authentication tokens, personal data and private site logs. Do not send production credentials or database dumps. If you cannot reproduce safely, report what you observed without further testing.

## Supported versions

Security fixes target the **latest stable release**. Older releases are not maintained as separate security branches. Please check the latest version when practical, but suspected vulnerabilities in older versions are still welcome. A report is not rejected solely because an upgrade is not possible.

## Response and disclosure

We aim to acknowledge reports within **5 business days** and provide an initial assessment or progress update within **10 business days**. These are response targets, not guaranteed resolution deadlines. If you have not received an acknowledgment, follow up through the fallback contact above.

We will assess the report, discuss practical mitigations where possible and coordinate public disclosure with the reporter after a fix or mitigation is available. Timing depends on impact and investigation; no fixed repair deadline is promised. Reporter credit is offered with consent. Do not include exploit details in a public issue while coordination is in progress.

## Testing boundaries

Test only systems and accounts you own or are explicitly authorized to assess. Prefer an isolated local or staging site. Avoid accessing other users' data, disrupting service, mass email, destructive actions or persistence. Stop if testing risks harm and report the observation privately.

## Product scope and limitations

This plugin groups notices from plugins and themes while leaving WordPress core notices in place. Third-party security notices may appear inside the grouped panel. It does not determine which notices are urgent, scan for malware or protect the site from vulnerabilities. Review grouped notices and configure verified exceptions where needed.

Security controls, automated checks and code reviews reduce risk; they are not a guarantee that the plugin or a WordPress site is free of vulnerabilities. Repository documentation describes intended behavior, not independent security certification.

## Verify the reporting route

The repeatable maintainer check is documented in [Security reporting verification](docs/security-reporting-verification.md). From a local checkout, run `python3 scripts/verify-security-policy.py --local` for document checks, or `python3 scripts/verify-security-policy.py` to also verify the published files and live GitHub setting. A successful check does not prove email delivery or application security.
