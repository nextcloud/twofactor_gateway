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
 * @psalm-type TwoFactorGatewayGroup = array{
 *     id: string,
 *     displayName: string,
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
 * @psalm-type TwoFactorGatewayAllowedActions = array{
 *     canView: bool,
 *     canCreateInstances: bool,
 *     canEditInstances: bool,
 *     canDeleteInstances: bool,
 *     canSetDefaultInstances: bool,
 *     canManageRouting: bool,
 *     canTestInstances: bool,
 *     canReorderInstances: bool,
 * }
 * @psalm-type TwoFactorGatewayAdminScreenItem = array{
 *     orderKey: string,
 *     gatewayId: string,
 *     providerName: string,
 *     fields: list<array<string, mixed>>,
 *     instance: array<string, mixed>,
 *     groupNames: list<string>,
 *     showRoutingAction: bool,
 * }
 * @psalm-type TwoFactorGatewayAdminScreen = array{
 *     gateways: list<array<string, mixed>>,
 *     groups: list<TwoFactorGatewayGroup>,
 *     allowedActions: TwoFactorGatewayAllowedActions,
 *     items: list<TwoFactorGatewayAdminScreenItem>,
 * }
 */
final class ResponseDefinitions {
}
