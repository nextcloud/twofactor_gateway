<!--
 - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Telegram CLI Commands

These commands are provided as a standalone CLI tool to integrate Telegram with LibreSign.

## Why separate from Nextcloud?

- **Nextcloud server** requires PHP **8.1** (minimum).  
- **danog/madelineproto** requires PHP **8.2** (minimum).  

Because of this mismatch, MadelineProto cannot run inside Nextcloud’s OCC commands without causing dependency conflicts.  

The solution is to run Telegram-related commands in a **separate process**, with their own dependencies and PHP runtime.

## Usage

- `telegram:login` – Authenticate a Telegram account (interactive).  
- `telegram:send` – Send a message to a user by username or phone number.  


## ⚠️ **Attention**

Once Nextcloud raises its minimum PHP requirement to **8.2 or higher**, this separation should be reviewed.

At that point, it may be possible to integrate these commands directly into `occ` or into the Nextcloud app itself, removing the need for a standalone CLI.
