# Changelog

## [0.0.2](https://github.com/openCoreEMR/oce-cli-manage-users/compare/0.0.1...0.0.2) (2026-05-21)


### Bug Fixes

* **commands:** render full stack trace when verbose ([#34](https://github.com/openCoreEMR/oce-cli-manage-users/issues/34)) ([24c9be0](https://github.com/openCoreEMR/oce-cli-manage-users/commit/24c9be0140787310aeb5946bf3787404616ffad3))
* **user-create:** use throwing QueryUtils variants so rollback fires ([#37](https://github.com/openCoreEMR/oce-cli-manage-users/issues/37)) ([f41a439](https://github.com/openCoreEMR/oce-cli-manage-users/commit/f41a439557605109aa83d61e836378499d92b488))
* **user:create:** register new user in OpenEMR ACL system ([#24](https://github.com/openCoreEMR/oce-cli-manage-users/issues/24)) ([715f63f](https://github.com/openCoreEMR/oce-cli-manage-users/commit/715f63f69867094d70efe0cab8d90b0daeeda91f)), closes [#23](https://github.com/openCoreEMR/oce-cli-manage-users/issues/23)
* **user:list:** throw on non-scalar column values ([#28](https://github.com/openCoreEMR/oce-cli-manage-users/issues/28)) ([84028ba](https://github.com/openCoreEMR/oce-cli-manage-users/commit/84028ba0dff0c2f3ce34f136eae13bb411be4eec))
* **user:reset-password:** generate policy-compliant random passwords ([#33](https://github.com/openCoreEMR/oce-cli-manage-users/issues/33)) ([23a082a](https://github.com/openCoreEMR/oce-cli-manage-users/commit/23a082ae1473fa4dccf12ccf49b083b6e57edec0))


### Documentation

* add CONTRIBUTING, issue/PR templates, README permissions note ([#27](https://github.com/openCoreEMR/oce-cli-manage-users/issues/27)) ([33bbfb2](https://github.com/openCoreEMR/oce-cli-manage-users/commit/33bbfb2c33af87db3f7b8077624e54a54b28b085)), closes [#20](https://github.com/openCoreEMR/oce-cli-manage-users/issues/20)
* point per-file license headers at this repo's LICENSE ([#30](https://github.com/openCoreEMR/oce-cli-manage-users/issues/30)) ([dfa3279](https://github.com/openCoreEMR/oce-cli-manage-users/commit/dfa32795a7ac05641f6e2c081275588eb09d1dc1)), closes [#16](https://github.com/openCoreEMR/oce-cli-manage-users/issues/16)


### Code Refactoring

* **connector:** split initialize into testable pieces ([#35](https://github.com/openCoreEMR/oce-cli-manage-users/issues/35)) ([9783e53](https://github.com/openCoreEMR/oce-cli-manage-users/commit/9783e537f6e8fa40d041e8341665a39c614c5ab0)), closes [#10](https://github.com/openCoreEMR/oce-cli-manage-users/issues/10)


### Build System

* exclude build.php from the PHAR ([#32](https://github.com/openCoreEMR/oce-cli-manage-users/issues/32)) ([a53e946](https://github.com/openCoreEMR/oce-cli-manage-users/commit/a53e9469c63d3142393be16acc9c24ddbb0e862b)), closes [#14](https://github.com/openCoreEMR/oce-cli-manage-users/issues/14)


### Dependencies

* **deps:** bump openCoreEMR/github-workflows-public/.github/workflows/actionlint.yml ([#38](https://github.com/openCoreEMR/oce-cli-manage-users/issues/38)) ([76284c1](https://github.com/openCoreEMR/oce-cli-manage-users/commit/76284c15dc93e8023bd8265a9a3a9e6f54628840))
* **deps:** bump openCoreEMR/github-workflows-public/.github/workflows/actionlint.yml ([#45](https://github.com/openCoreEMR/oce-cli-manage-users/issues/45)) ([1c02151](https://github.com/openCoreEMR/oce-cli-manage-users/commit/1c021516f9a212beb569cc5e4e544fa6889762e8))
* **deps:** bump openCoreEMR/github-workflows-public/.github/workflows/conventional-pr-title.yml ([#46](https://github.com/openCoreEMR/oce-cli-manage-users/issues/46)) ([143a064](https://github.com/openCoreEMR/oce-cli-manage-users/commit/143a0648fc186d2264f936b42699afc18124c537))
* **deps:** bump openCoreEMR/github-workflows-public/.github/workflows/dclint.yml ([#40](https://github.com/openCoreEMR/oce-cli-manage-users/issues/40)) ([206de19](https://github.com/openCoreEMR/oce-cli-manage-users/commit/206de1971cc3eb6820dfaaf5d6ee58af97c5345a))
* **deps:** bump openCoreEMR/github-workflows-public/.github/workflows/php-composer-script.yml ([#41](https://github.com/openCoreEMR/oce-cli-manage-users/issues/41)) ([87a5b14](https://github.com/openCoreEMR/oce-cli-manage-users/commit/87a5b14caa00635daa7a0a2f193150492a6a6bb9))
* **deps:** bump openCoreEMR/github-workflows-public/.github/workflows/php-composer-script.yml ([#44](https://github.com/openCoreEMR/oce-cli-manage-users/issues/44)) ([2a47aff](https://github.com/openCoreEMR/oce-cli-manage-users/commit/2a47afffe1f00bde7c1ff1ca86022b588d7362cf))
* **deps:** bump openCoreEMR/github-workflows-public/.github/workflows/php-tests.yml ([#39](https://github.com/openCoreEMR/oce-cli-manage-users/issues/39)) ([65eac70](https://github.com/openCoreEMR/oce-cli-manage-users/commit/65eac70de4a9508a3ff133268c233b23f1034331))
* **deps:** bump opencoreemr/github-workflows-public/.github/workflows/release-please-reusable.yml ([#25](https://github.com/openCoreEMR/oce-cli-manage-users/issues/25)) ([917b514](https://github.com/openCoreEMR/oce-cli-manage-users/commit/917b514aafbc9b6be08bcc95308000400ce4568b))
* **deps:** bump opencoreemr/github-workflows-public/.github/workflows/release-please-reusable.yml ([#42](https://github.com/openCoreEMR/oce-cli-manage-users/issues/42)) ([2f51c6c](https://github.com/openCoreEMR/oce-cli-manage-users/commit/2f51c6c07f3a99c8f619034dc791caa6aa496640))
* **deps:** bump opencoreemr/github-workflows-public/.github/workflows/release-please-reusable.yml ([#47](https://github.com/openCoreEMR/oce-cli-manage-users/issues/47)) ([000ca51](https://github.com/openCoreEMR/oce-cli-manage-users/commit/000ca51f3379a0de5771f763afb94cf29bd90f52))

## [0.0.1](https://github.com/openCoreEMR/oce-cli-manage-users/compare/0.0.0...0.0.1) (2026-05-08)


### Features

* initial CLI for managing OpenEMR users ([#1](https://github.com/openCoreEMR/oce-cli-manage-users/issues/1)) ([0ef0ebb](https://github.com/openCoreEMR/oce-cli-manage-users/commit/0ef0ebb154ec8632245dd08cac19d32fd29b4c1a))
