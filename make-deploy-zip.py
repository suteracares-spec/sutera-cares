#!/usr/bin/env python3
"""Build the zip that gets uploaded to cPanel.

    python make-deploy-zip.py

Produces suteracares-deploy.zip with the site files at the top level,
so extracting it inside public_html drops them straight into place.

Working files (.git, .vercel, README.md, this script) are left out
deliberately — only what the public should be able to fetch goes in.
"""

import os
import sys
import zipfile

ROOT = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(ROOT, "suteracares-deploy.zip")

FILES = [
    ".htaccess",
    "index.html",
    "404.html",
    "robots.txt",
    "sitemap.xml",
    "css/styles.css",
    "js/i18n.js",
    "img/favicon.svg",
    "img/logo.svg",
]


def main():
    missing = [f for f in FILES if not os.path.isfile(os.path.join(ROOT, f))]
    if missing:
        sys.exit("Missing files, nothing written: " + ", ".join(missing))

    if os.path.exists(OUT):
        os.remove(OUT)

    with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as z:
        for name in FILES:
            z.write(os.path.join(ROOT, name), arcname=name)

    total = sum(os.path.getsize(os.path.join(ROOT, f)) for f in FILES)
    print("Wrote {} — {} files, {:.0f} KB raw, {:.0f} KB zipped".format(
        os.path.basename(OUT), len(FILES), total / 1024,
        os.path.getsize(OUT) / 1024))
    for name in FILES:
        print("  " + name)


if __name__ == "__main__":
    main()
