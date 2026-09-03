# PluginPulse Connect

<!-- tooling:start (managed by wordpress-plugin-boilerplate/tooling - do not edit by hand) -->
## Development

This repository uses the standard Fullworks free-plugin tooling, documented in
[wordpress-plugin-boilerplate](https://github.com/alanef/wordpress-plugin-boilerplate/blob/main/CLAUDE.md).

[![Plugin Check](https://github.com/alanef/plugin-fullworks-support-diagnostics-project/actions/workflows/checks.yml/badge.svg)](https://github.com/alanef/plugin-fullworks-support-diagnostics-project/actions/workflows/checks.yml)

```
plugin-fullworks-support-diagnostics-project/                     # repository root: development tooling
├── .github/workflows/             # checks.yml on push/PR, release.yml on tag
├── tests/                         # PHPUnit suite, run inside wp-env
├── .wp-env.json                   # dev :8750, tests :8751
├── composer.json                  # dev dependencies and quality scripts
├── package.json                   # wp-env and test scripts
├── phpunit.xml.dist / run-tests.sh
└── fullworks-support-diagnostics/                # the plugin (shipped as-is via .distignore)
```

```bash
composer install && npm install        # dev tools
npm run start                          # http://localhost:8750  (admin / password)
composer run check                     # PHPCompatibility + security sniffs
npm test                               # PHPUnit in the wp-env tests container
composer run build                     # zipped/fullworks-support-diagnostics-free.zip
```

Releases: set the version in the plugin header and `readme.txt`, update `CHANGELOG.md`,
tag `vX.Y.Z` and push. CI builds the zip, creates the GitHub release and deploys to
WordPress.org.
<!-- tooling:end -->
