# Changelog
All notable changes to this project will be documented in this file.

## 0.20.0 - 2020-08-26
(no code changes, just version bump)
### Added
- Nextcloud 24 support
- PHP 8.1 support
### Removed
- Nextcloud 18-20 support

## (0.18.0 / 0.19.0 missing data)
### Added
- Nextcloud 23 support
- PHP 8.0 support
### Removed
- PHP 7.2 support

## 0.17.0 - 2020-08-26
### Added
- Nextcloud 20 support
- Ability to remove gateways via the CLI
- ClickSend.com SMS gateway
### Removed
- Nextcloud 16 support
- Nextcloud 17 support

## 0.16.0 - 2020-04-17
### Added
- Nextcloud 19 support
- Clickatell.com gateway support
- OVH SMS gateway support
### Changed
- New and updated translations
- Updated dependencies
### Fixed
- Problems with sms77

## 0.15.1 – 2020-01-07
### Changed
- New and updated translations
- Updated dependencies

## 0.15.0 – 2019-12-10
### Added
- Spryng gateway support
- Sms77io gateway support
- Nextcloud 18 support
- php7.4 support
### Changed
- ecall API updated to new version
- New and updated translations
### Removed
- php7.1 support

## 0.14.1 – 2019-08-28
### Changed
- New and updated translations
### Fixed
- Vulnerabilities in npm dependencies

## 0.14.0 – 2019-05-14
### Added
- Nextcloud 16 support
### Fixed
- Use Telegram user/chat ID as entered by user to fix many edge cases
### Removed
- Nextcloud 15 support
- php7.0 support

## 0.13.0 – 2019-04-05
### Added
- Huawei E3531 3G stick support
- New and updated translations
- Move the personal settings to the general 2FA section

## 0.12.0 – 2019-02-13
### Added
- PHP 7.3 support
- Nextcloud 16 support
- ecall support
- voip.ms support
- New and updated translations
- User documentation
- CLI setup command parameter description
### Changed
- Better Telegram setup description
### Removed
- Nextcloud 14 support

## 0.11.0 – 2018-11-16
### Added
- PuzzelSMS support
- New and updated translations

## 0.10.1 – 2018-11-12
### Fixed
- Software dependencies updated

## 0.10.0 – 2018-10-08
### Added
- Support for ClockworkSMS
- Admin documentation reference in info.xml (also rendered on apps.nextcloud.com)
### Fixed
- Loading animation after provider state has been loaded
- Caching of Telegram chat id (provider stopped working after a few days)
- Removed dead code

## 0.9.0 – 2018-08-30
### Added
- Setup instructions for users directly in the settings UI
- Updated admin documentation
- New and updated translations
### Fixed
- Undefined variable warnings in the AProvider class
- Hide gateway settings if it's not configured by the admin

## 0.8.0 – 2018-08-24
### Added
- Ability to enable all three gateways (Signal, SMS, Telegram) at the same time
- An occ command to get the configuration status
- An occ command to interactively configure the gateways
- Ability to set a custom URL for the Signal gateway
### Fixed
- Fatal errors if no provider has been configured

## 0.7.0
### Added
- First working implementation
- Stable gateways: websms.de
- Unstable/experimental gateways: PlaySMS, Signal, Telegram
- UI to enter gateway identifier (e.g. phone number)
- Support for Nextcloud 14
