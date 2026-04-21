<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway;

/**
 * @psalm-type TwoFactorGatewayState = array{
 *     gatewayName: string,
 *     state: int,
 *     phoneNumber: ?string,
 * }
 * @psalm-type TwoFactorGatewayCapabilities = array{
 *     features: list<string>,
 *     config: array{
 *     },
 *     version: string,
 * }
 * @psalm-type TwoFactorGatewayFieldDefinition = array{
 *     field: string,
 *     prompt: string,
 *     default: string,
 *     optional: bool,
 *     type: ?string,
 *     hidden: bool,
 *     min: ?int,
 *     max: ?int,
 *     helper: string,
 * }
 * @psalm-type TwoFactorGatewayProviderCatalogEntry = array{
 *     id: string,
 *     name: string,
 *     fields: list<TwoFactorGatewayFieldDefinition>,
 * }
 * @psalm-type TwoFactorGatewayInstance = array{
 *     id: string,
 *     label: string,
 *     default: bool,
 *     createdAt: string,
 *     config: array<string, string>,
 *     isComplete: bool,
 *     groupIds: list<string>,
 *     priority: int,
 * }
 * @psalm-type TwoFactorGatewayGatewayInfo = array{
 *     id: string,
 *     name: string,
 *     instructions: string,
 *     allowMarkdown: bool,
 *     fields: list<TwoFactorGatewayFieldDefinition>,
 *     instances: list<TwoFactorGatewayInstance>,
 *     providerSelector?: TwoFactorGatewayFieldDefinition,
 *     providerCatalog?: list<TwoFactorGatewayProviderCatalogEntry>,
 * }
 */
final class ResponseDefinitions {
}
