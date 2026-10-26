#!/usr/bin/env python3
"""Inventory bundled PHP libraries and declared Actions refs; optionally submit to GitHub."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
from urllib.parse import quote

ROOT = Path(__file__).resolve().parents[1]


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT, text=True).strip()


def api(endpoint, payload=None):
    args = ['gh', 'api', endpoint]
    if payload is not None:
        args += ['--method', 'POST', '--input', '-']
    result = subprocess.run(args, input=json.dumps(payload) if payload is not None else None,
                            capture_output=True, text=True, cwd=ROOT)
    if result.returncode:
        raise RuntimeError('GitHub request failed for ' + endpoint + ': ' + result.stderr.strip())
    return json.loads(result.stdout)


def identity():
    match = re.fullmatch(r'(?:https://github\.com/|git@github\.com:)([^/\s]+/[^/\s]+?)(?:\.git)?/?', git('remote', 'get-url', 'origin'))
    if not match:
        raise RuntimeError('Expected a GitHub origin.')
    return match.group(1)


def inventory():
    packages = {}
    manual = []
    library = ROOT / 'plugin-update-checker/plugin-update-checker.php'
    if library.exists():
        match = re.search(r'Plugin Update Checker Library\s+([0-9.]+)', library.read_text())
        if not match:
            raise RuntimeError('Cannot determine bundled Plugin Update Checker version.')
        version = match.group(1)
        parent = 'pkg:composer/yahnis-elsts/plugin-update-checker@' + version
        packages[parent] = {'package_url': parent, 'relationship': 'direct', 'scope': 'runtime', 'dependencies': [],
                            'metadata': {'version_basis': 'bundled declaration; local modifications possible',
                                         'source_sha256': hashlib.sha256(library.read_bytes()).hexdigest()}}
        parser = ROOT / 'plugin-update-checker/vendor/ParsedownModern.php'
        if not parser.exists():
            raise RuntimeError('Expected bundled Markdown parser is missing; review inventory rules.')
        match = re.search(r"const\s+version\s*=\s*['\"]([0-9.]+)['\"]", parser.read_text())
        if not match:
            raise RuntimeError('Cannot determine bundled Parsedown declaration.')
        child = 'pkg:composer/erusev/parsedown@' + match.group(1)
        packages[parent]['dependencies'].append(child)
        packages[child] = {'package_url': child, 'relationship': 'indirect', 'scope': 'runtime',
                           'metadata': {'version_basis': 'declared upstream version; bundled copy may be modified',
                                        'source_sha256': hashlib.sha256(parser.read_bytes()).hexdigest()}}
        readme = ROOT / 'plugin-update-checker/vendor/PucReadmeParser.php'
        if readme.exists():
            manual.append({'path': str(readme.relative_to(ROOT)), 'reason': 'Modified readme parser has no declared package version; manual review required.',
                           'sha256': hashlib.sha256(readme.read_bytes()).hexdigest()})
    # Only committed workflows are relevant. No YAML execution or third-party parser is needed.
    workflows = git('ls-files', '.github/workflows').splitlines()
    for name in workflows:
        path = ROOT / name
        if path.suffix not in ('.yml', '.yaml'):
            continue
        for action in re.findall(r'^\s*(?:-\s*)?uses:\s*[\'\"]?([^\s\'\"#]+)', path.read_text(), re.MULTILINE):
            match = re.fullmatch(r'([\w.-]+/[\w.-]+)(?:/[\w./-]+)?@([\w./-]+)', action)
            if match:
                purl = 'pkg:githubactions/' + match.group(1).lower() + '@' + quote(match.group(2), safe='')
                packages[purl] = {'package_url': purl, 'relationship': 'direct', 'scope': 'development',
                                  'metadata': {'version_basis': 'declared workflow ref; a tag is not an immutable version'}}
            elif not action.startswith('./'):
                manual.append({'path': name, 'reason': 'External action form is not recognized: ' + action})
    return packages, manual


def snapshot(repo, packages):
    manifests = {}
    runtime = {k: v for k, v in packages.items() if v['scope'] == 'runtime'}
    tools = {k: v for k, v in packages.items() if v['scope'] == 'development'}
    if runtime:
        manifests['bundled-php-libraries'] = {'name': 'Bundled PHP libraries (declared versions)',
            'file': {'source_location': 'plugin-update-checker/plugin-update-checker.php'}, 'resolved': runtime}
    if tools:
        manifests['github-actions'] = {'name': 'Declared GitHub Actions refs',
            'file': {'source_location': '.github/workflows/dependency-inventory.yml'}, 'resolved': tools}
    return {'version': 0, 'sha': git('rev-parse', 'HEAD'), 'ref': 'refs/heads/main',
            'job': {'id': os.environ.get('GITHUB_RUN_ID', 'manual-' + git('rev-parse', '--short', 'HEAD')),
                    'correlator': 'thrg-dependency-inventory'},
            'detector': {'name': 'thrg-dependency-inventory', 'version': '1.0.0', 'url': 'https://github.com/' + repo},
            'scanned': datetime.now(timezone.utc).isoformat(), 'manifests': manifests}


def verify(repo, packages):
    owner, name = repo.split('/')
    query = ('query { repository(owner:' + json.dumps(owner) + ',name:' + json.dumps(name) + ') { '
             'dependencyGraphManifests(first:100) { totalCount nodes { filename dependencies(first:100) '
             '{ totalCount nodes { packageManager packageName requirements } } } } } }')
    data = api('graphql', {'query': query})
    if data.get('errors'):
        raise RuntimeError('Dependency graph query did not complete.')
    manifests = data['data']['repository']['dependencyGraphManifests']
    if manifests['totalCount'] > 100 or any(x['dependencies']['totalCount'] > 100 for x in manifests['nodes']):
        raise RuntimeError('Graph exceeds this verifier pagination bound; extend it before claiming coverage.')
    seen = [(d['packageName'].lower(), d.get('requirements') or '')
            for m in manifests['nodes'] for d in m['dependencies']['nodes']]
    missing = []
    for purl in packages:
        package, version = purl.split('/', 1)[1].rsplit('@', 1)
        # GitHub may normalize the displayed name; preserve version/ref matching.
        if not any(name == package.lower() and version in requirement for name, requirement in seen):
            missing.append(purl)
    if missing:
        raise RuntimeError('Expected dependencies not yet indexed: ' + ', '.join(missing))
    print('PASS GitHub dependency graph contains all', len(packages), 'expected version/ref entries for', repo)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--submit', action='store_true', help='Publish a dependency snapshot to this GitHub repository')
    parser.add_argument('--verify', action='store_true', help='Read GitHub graph and require expected dependencies to be indexed')
    parser.add_argument('--output', type=Path, help='Write the local inventory and manual-review gaps as JSON')
    args = parser.parse_args()
    repo = identity()
    packages, manual = inventory()
    report = {'repository': repo, 'packages': packages, 'manual_review': manual,
              'limits': 'Declared bundled versions/Actions refs only. No assertion of pristine upstream bytes, complete transitive dependencies, development CLI resolution, or application security.'}
    if args.output:
        args.output.write_text(json.dumps(report, indent=2) + '\n')
    print(repo + ': ' + str(len(packages)) + ' version/ref entries; ' + str(len(manual)) + ' manual-review items')
    if args.submit:
        if git('branch', '--show-current') != 'main':
            raise RuntimeError('Submit only from the main branch.')
        result = api('repos/' + repo + '/dependency-graph/snapshots', snapshot(repo, packages))
        if result.get('result') != 'SUCCESS':
            raise RuntimeError('GitHub did not confirm snapshot success.')
        print('Snapshot accepted:', result['id'])
    if args.verify:
        verify(repo, packages)


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, OSError, ValueError, subprocess.SubprocessError) as error:
        raise SystemExit(str(error))
