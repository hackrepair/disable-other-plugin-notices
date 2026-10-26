#!/usr/bin/env python3
"""Read-only checks for local and published security-reporting configuration."""
import argparse
import base64
import json
from pathlib import Path
import re
import subprocess
import sys
from urllib.parse import quote

ROOT = Path(__file__).resolve().parents[1]


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def github_json(endpoint):
    result = subprocess.run(["gh", "api", endpoint], capture_output=True, text=True)
    require(result.returncode == 0, "GitHub API check failed; confirm gh authentication and repository access.")
    return json.loads(result.stdout)


def remote_text(repo, name, branch):
    result = github_json("repos/" + repo + "/contents/" + name + "?ref=" + quote(branch, safe=""))
    require(result.get("encoding") == "base64", "Unexpected GitHub file encoding: " + name)
    return base64.b64decode(result["content"]).decode("utf-8")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--local", action="store_true", help="Check local documents without contacting GitHub")
    args = parser.parse_args()
    origin = subprocess.check_output(["git", "remote", "get-url", "origin"], cwd=ROOT, text=True).strip()
    match = re.fullmatch(r"(?:https://github\.com/|git@github\.com:)([^/\s]+/[^/\s]+?)(?:\.git)?/?", origin)
    require(match is not None, "Expected a GitHub origin; check the repository identity manually.")
    repo = match.group(1)
    policy = (ROOT / "SECURITY.md").read_text()
    readme = (ROOT / "README.md").read_text()
    url = "https://github.com/" + repo + "/security/advisories/new"
    for heading in ("Report a vulnerability privately", "What to include", "Supported versions", "Response and disclosure", "Testing boundaries", "Product scope and limitations", "Verify the reporting route"):
        require("## " + heading in policy, "Missing policy section: " + heading)
    require(url in policy, "Private-reporting URL does not match origin.")
    urls = re.findall(r"https://github\.com/[^\s)]+/security/advisories/new", policy)
    require(urls == [url], "Unexpected or duplicate private-reporting destination.")
    require("mailto:jim@hackrepair.com" in policy, "Missing maintainer fallback contact.")
    require("5 business days" in policy and "10 business days" in policy and "not guaranteed" in policy, "Missing qualified response targets.")
    require("latest stable release" in policy, "Missing supported-version policy.")
    require("[Security reporting](SECURITY.md)" in readme, "README does not link to the policy.")
    require((ROOT / "docs/security-reporting-verification.md").is_file(), "Missing verification procedure.")
    print("PASS local policy, repository destination, fallback and README:", repo)
    if args.local:
        return
    branch = github_json("repos/" + repo)["default_branch"]
    require(remote_text(repo, "SECURITY.md", branch) == policy, "Published default-branch policy differs from this checkout.")
    require("[Security reporting](SECURITY.md)" in remote_text(repo, "README.md", branch), "Published README is missing the policy link.")
    require(github_json("repos/" + repo + "/private-vulnerability-reporting").get("enabled") is True, "GitHub private vulnerability reporting is disabled.")
    print("PASS published policy, README and enabled private reporting on", branch)
    print("Separate checks: signed-in report form and authorized mailbox-delivery confirmation. No report or email was sent.")


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, OSError, ValueError, subprocess.SubprocessError) as error:
        print("FAIL:", str(error), file=sys.stderr)
        sys.exit(1)
