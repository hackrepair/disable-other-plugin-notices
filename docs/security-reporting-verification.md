# Verify security reporting

Run before each release and after changes to repository ownership, visibility, policy or reporting settings. Repeat if a reporter cannot reach the form.

1. Run `python3 scripts/verify-security-policy.py --local` to check the policy structure, exact repository reporting URL, fallback email and README link.
2. Sign in to GitHub CLI with an account that can inspect repository settings, then run `python3 scripts/verify-security-policy.py`. It checks the **default branch**, compares the published policy with this checkout, verifies the published README policy link, and reads GitHub's private-vulnerability-reporting setting. It exits nonzero on failure and makes no changes. It does not print access tokens or read vulnerability reports.
3. In a signed-in browser, follow the private-reporting link in SECURITY.md and confirm a private report form is available. Check the repository name. **Do not submit a dummy vulnerability or create an advisory.** A login screen alone is not verification of the form. An owner account's result does not establish access for every other account.
4. Inspect the `mailto:` fallback. Confirm that the maintainer controls and monitors that mailbox. Inbox delivery needs a separate, explicitly authorized test; document the recipient's confirmation rather than assuming a link proves delivery.
5. Record the date, commit, API result, browser result and any remaining limitations. Keep report contents out of public verification records. If the API or form check fails, fix the settings or policy link and repeat the check before claiming it works. A delivery test or security audit is not performed by this script.

## What this verifies

Policy consistency, a published reporting destination and the live GitHub feature setting. It does not establish exploitability, application security, email delivery, acknowledgment performance or external-account access. Review the response targets with the maintainer rather than treating them as a service-level guarantee.
