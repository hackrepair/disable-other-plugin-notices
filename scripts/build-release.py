#!/usr/bin/env python3
"""Build a complete plugin ZIP, preserving bundled updater dependencies."""
from pathlib import Path
import argparse, fnmatch, hashlib, re, zipfile
p=argparse.ArgumentParser();p.add_argument('--output',type=Path);a=p.parse_args()
root=Path(__file__).resolve().parents[1]
main=next(f for f in root.glob('*.php') if re.search(r'Plugin Name:',f.read_text()))
slug=main.stem if main.stem != 'index' else root.name
version=re.search(r'Version:\s*([0-9.]+)',main.read_text()).group(1)
output=a.output or root/'dist'/(slug+'.'+version+'.zip');output.parent.mkdir(parents=True,exist_ok=True)
excluded={'.git','.github','.review','tests','scripts','dist','releases','node_modules','vendor','docs','.wordpress-org'}
excluded_files={'.DS_Store','.gitignore','.editorconfig','.distignore','phpcs.xml.dist','README.md','PROJECT.md','PROJECT_UPDATES.md'}
patterns=[]
if (root/'.distignore').exists():patterns=[s.strip().lstrip('/') for s in (root/'.distignore').read_text().splitlines() if s.strip() and not s.startswith('#')]
files=[]
for f in root.rglob('*'):
 if not f.is_file():continue
 rel=f.relative_to(root)
 if rel.parts[0]!='plugin-update-checker' and (rel.parts[0] in excluded or f.name in excluded_files or any(fnmatch.fnmatch(str(rel),x) or str(rel).startswith(x.rstrip('/')+'/') for x in patterns)):continue
 files.append(f)
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as z:
 for f in files:z.write(f,slug+'/'+str(f.relative_to(root)))
with zipfile.ZipFile(output) as z:
 assert z.testzip() is None
 assert {n.split('/')[0] for n in z.namelist()}=={slug}
 for f in files:assert z.read(slug+'/'+str(f.relative_to(root)))==f.read_bytes()
 for f in (root/'plugin-update-checker').rglob('*'):
  if f.is_file():assert z.read(slug+'/'+str(f.relative_to(root)))==f.read_bytes()
 assert slug+'/includes/companion-plugins.php' in z.namelist()
digest=hashlib.sha256(output.read_bytes()).hexdigest()
output.with_suffix(output.suffix+'.sha256').write_text(digest+'  '+output.name+'\n')
print(output);print(digest)
