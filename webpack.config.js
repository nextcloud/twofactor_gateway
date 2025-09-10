/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { merge } = require('webpack-merge');
const webpackConfig = require('@nextcloud/webpack-vue-config')

module.exports = merge([
  webpackConfig,
  {
    entry: './src/main.js',
    output: {
      filename: 'build.js',
    }
  }
])