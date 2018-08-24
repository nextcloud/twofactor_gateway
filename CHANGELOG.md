# Changelog
All notable changes to this project will be documented in this file.

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
