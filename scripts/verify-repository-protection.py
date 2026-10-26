#!/usr/bin/env python3
"""Read-only live checks for repository protections and indexed dependency coverage."""
import json
from pathlib import Path
import re
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
try:
    origin = subprocess.check_output(['git','remote','get-url','origin'],cwd=root,text=True).strip()
    match = re.fullmatch(r'(?:https://github\.com/|git@github\.com:)([^/\s]+/[^/\s]+?)(?:\.git)?/?',origin)
    if not match:
        raise RuntimeError('Expected a GitHub repository origin.')
    repo = match.group(1)
    data = json.loads(subprocess.check_output(['gh','api','repos/'+repo],text=True))
    for feature in ['secret_scanning','secret_scanning_push_protection']:
        if data.get('security_and_analysis',{}).get(feature,{}).get('status') != 'enabled':
            raise RuntimeError(feature+' is not enabled, or this account cannot inspect it.')
    result = subprocess.run(['gh','api','repos/'+repo+'/vulnerability-alerts'],capture_output=True,text=True)
    if result.returncode:
        raise RuntimeError('Dependabot alerts are disabled or not accessible to this account.')
    print('PASS secret scanning, push protection and Dependabot alerts:',repo,flush=True)
    subprocess.run([sys.executable,str(root/'scripts/dependency-inventory.py'),'--verify'],cwd=root,check=True)
    print('Limits: configured protections and indexed declared dependencies only; not a credential-blocking test or security audit.')
except (RuntimeError,OSError,ValueError,subprocess.SubprocessError) as error:
    raise SystemExit('FAIL: '+str(error))
