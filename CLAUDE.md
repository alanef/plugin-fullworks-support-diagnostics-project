<!-- tooling:start (managed by wordpress-plugin-boilerplate/tooling - do not edit by hand) -->
# PluginPulse Connect - Development Guide

Tooling in this repository is standardised across the Fullworks free plugins. The master
description lives in
[wordpress-plugin-boilerplate/CLAUDE.md](https://github.com/alanef/wordpress-plugin-boilerplate/blob/main/CLAUDE.md).
**Fix tooling problems there first, then roll out** with its `bin/sync-tooling.sh`; never
hand-edit the managed files listed there.

## This repository

| | |
|---|---|
| Plugin directory | `fullworks-support-diagnostics/` |
| Main file | `fullworks-support-diagnostics/fullworks-support-diagnostics.php` |
| Default branch | `master` |
| WordPress.org slug | `fullworks-support-diagnostics` |
| wp-env ports | dev `8750`, tests `8751` |
| Version locations | plugin header `Version:`, `readme.txt` `Stable tag:` and `WPSA_PLUGIN_VERSION` in the main file |

CI fails when the version locations disagree.

## Commands

```bash
composer install && npm install   # first time
composer run check                # PHPCompatibility + WordPress security sniffs
npm run start                     # wp-env (dev :8750, tests :8751, admin/password)
npm test                          # PHPUnit inside the wp-env tests container
npm test -- --filter Foo          # pass PHPUnit args through
composer run build                # zipped/fullworks-support-diagnostics-free.zip via wp dist-archive
```

## Release

1. Update `CHANGELOG.md` (move Unreleased to the version and date).
2. Set the version in every location above (no prerelease suffix).
3. `composer run check && npm test`.
4. Commit, tag `vX.Y.Z`, push branch and tag.
5. The `Build Release` workflow re-runs the checks, creates the GitHub release with the zip
   attached and deploys trunk + tag to WordPress.org SVN (needs `SVN_USERNAME` and
   `SVN_PASSWORD` repository secrets).
<!-- tooling:end -->

# Claude Context

## Project Relationship

This plugin project is part of the **YouTrack PAG project**.

## Related Projects

- Main Laravel Project: `/home/alan/projects/github.com/alanef/pageprism-laravel-project`

## Project Purpose

This is a WordPress plugin for Fullworks Support Diagnostics, which integrates with the PagePrism Laravel application.