#!/usr/bin/env python3
"""Run every p85-chunk*.py merge (excluding wire scripts) to sync de from chunk catalogs."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = ROOT / "scripts"


def main() -> None:
    chunks = sorted(
        p
        for p in SCRIPTS.glob("p85-chunk*.py")
        if p.is_file() and "wire" not in p.name
    )
    if not chunks:
        print("No chunk scripts found", file=sys.stderr)
        sys.exit(1)
    for path in chunks:
        print(f"Running {path.name}…")
        subprocess.run([sys.executable, str(path)], check=True, cwd=ROOT)
    print(f"Merged {len(chunks)} chunk script(s)")


if __name__ == "__main__":
    main()
