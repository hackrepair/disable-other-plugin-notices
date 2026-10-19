#!/usr/bin/env python3
"""A restrictive source mode must not leak into the installable ZIP."""
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import zipfile

builder = Path(__file__).resolve().parents[1] / "scripts/build-release.py"
with tempfile.TemporaryDirectory() as tmp:
    root = Path(tmp) / "fixture"
    for directory in ("scripts", "assets", "includes", "plugin-update-checker/vendor"):
        (root / directory).mkdir(parents=True, exist_ok=True)
    shutil.copy2(builder, root / "scripts/build-release.py")
    sources = {
        "index.php": "<?php /* Plugin Name: Fixture\nVersion: 1.0.0 */",
        "assets/inspector.css": ".fixture { color: red; }",
        "includes/companion-plugins.php": "<?php // fixture",
        "plugin-update-checker/vendor/fixture.php": "<?php // dependency",
    }
    for name, content in sources.items():
        path = root / name
        path.write_text(content)
        path.chmod(0o600)
    subprocess.run([sys.executable, str(root / "scripts/build-release.py")], check=True, capture_output=True)
    with zipfile.ZipFile(root / "dist/fixture.1.0.0.zip") as package:
        assert package.testzip() is None
        for name, content in sources.items():
            info = package.getinfo("fixture/" + name)
            assert info.create_system == 3
            assert (info.external_attr >> 16) & 0o7777 == 0o644, name
            assert package.read(info) == content.encode(), name
            assert (root / name).stat().st_mode & 0o777 == 0o600
print("PASS restricted source files packaged as 0644; content and source modes preserved")
