# Changelog
All notable changes to this project will be documented in this file.

## 0.12.0 – 2019-02-13
### Added
- ph7.3 support
- Nextcloud 16 support
- ecall support
- voip.sms support
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
