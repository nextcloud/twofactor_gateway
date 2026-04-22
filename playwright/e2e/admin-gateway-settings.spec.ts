// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { createGatewayInstance, deleteGatewayInstance, listGatewayInstances } from '../support/gateway-api'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

test.beforeEach(async ({ page }) => {
	await login(page.request, adminUser, adminPassword)
})

const openAdminSettings = async (page: Parameters<typeof test>[0]['page']) => {
	await page.goto('./settings/admin/security')
	await expect(page.locator('#twofactor-gateway-admin')).toBeVisible()
	await expect(page.locator('.admin-settings__content')).toBeVisible()
}
test('admin settings page renders the gateway list', async ({ page }) => {
	await openAdminSettings(page)
	await expect(page.locator('.admin-settings__actions')).toBeVisible()
})

test('admin can create a new gateway instance', async ({ page, request }, testInfo) => {
	await openAdminSettings(page)
	const label = `Playwright Test ${testInfo.workerIndex}-${Date.now()}`

	// Click Add provider configuration
	await page.getByRole('button', { name: 'Add provider configuration' }).click()
	await expect(page.locator('.gateway-instance-modal')).toBeVisible()

	// Select Signal provider in NcSelect
	const gatewaySelectInput = page.locator('#gateway-select input[type="search"], #gateway-select input').first()
	await gatewaySelectInput.click()
	await gatewaySelectInput.fill('Signal')
	await gatewaySelectInput.press('Enter')

	// Fill the label
	await page.locator('.gateway-instance-modal').getByLabel('Label').fill(label)

	// Fill the URL field
	await page.locator('.gateway-instance-modal').getByLabel('Gateway URL').fill('http://signal.example.com')

	// Save
	await page.locator('.gateway-instance-modal').getByRole('button', { name: 'Save' }).click()
	await expect(page.locator('.gateway-instance-modal')).not.toBeVisible()

	// The new instance should appear in the list
	await expect(page.locator('.gateway-instance-card', { hasText: label })).toBeVisible()

	// Cleanup via API
	const instances = await listGatewayInstances(request, adminUser, adminPassword, 'signal')
	for (const i of instances.filter((i) => i.label === label)) {
		await deleteGatewayInstance(request, adminUser, adminPassword, 'signal', i.id)
	}
})

test('admin can edit an existing gateway instance', async ({ page, request }) => {
	// Setup: create an instance via API
	const instance = await createGatewayInstance(
		request, adminUser, adminPassword,
		'signal', 'Original Label', { url: 'http://original.example.com' },
	)

	await openAdminSettings(page)
	await expect(page.locator('.gateway-instance-card', { hasText: 'Original Label' })).toBeVisible({ timeout: 15000 })

	// Click Edit button on the instance card
	await page.locator('.gateway-instance-card', { hasText: 'Original Label' })
		.getByRole('button', { name: 'Edit' }).click()
	await expect(page.locator('.gateway-instance-modal')).toBeVisible()

	// Update the label
	const labelInput = page.locator('.gateway-instance-modal').getByLabel('Label')
	await labelInput.clear()
	await labelInput.fill('Updated Label')

	// Save
	await page.locator('.gateway-instance-modal').getByRole('button', { name: 'Save' }).click()
	await expect(page.locator('.gateway-instance-modal')).not.toBeVisible()

	// The updated label should be visible
	await expect(page.locator('.gateway-instance-card', { hasText: 'Updated Label' })).toBeVisible()

	await deleteGatewayInstance(request, adminUser, adminPassword, 'signal', instance.id)
})

test('admin can delete a gateway instance', async ({ page, request }) => {
	// Setup: create an instance via API
	const instance = await createGatewayInstance(
		request, adminUser, adminPassword,
		'signal', 'To Be Deleted', { url: 'http://todelete.example.com' },
	)

	await openAdminSettings(page)
	await expect(page.locator('.gateway-instance-card', { hasText: 'To Be Deleted' })).toBeVisible({ timeout: 15000 })

	// Click Delete button
	await page.locator('.gateway-instance-card', { hasText: 'To Be Deleted' })
		.getByRole('button', { name: 'Delete' }).click()

	// Confirm deletion in the dialog
	await expect(page.locator('[role="dialog"], .nc-dialog')).toBeVisible()
	await page.getByRole('button', { name: /delete|confirm/i }).last().click()

	// The card should be gone
	await expect(page.locator('.gateway-instance-card', { hasText: 'To Be Deleted' })).not.toBeVisible()

	await deleteGatewayInstance(request, adminUser, adminPassword, 'signal', instance.id)
})

test('admin can set an instance as the default', async ({ page, request }) => {
	// Setup: create two instances via API
	const first = await createGatewayInstance(
		request, adminUser, adminPassword,
		'signal', 'First Instance', { url: 'http://first.example.com' },
	)
	const second = await createGatewayInstance(
		request, adminUser, adminPassword,
		'signal', 'Second Instance', { url: 'http://second.example.com' },
	)

	await openAdminSettings(page)
	await expect(page.locator('.gateway-instance-card', { hasText: 'Second Instance' })).toBeVisible({ timeout: 15000 })

	// Click "Set as default" on Second Instance
	await page.locator('.gateway-instance-card', { hasText: 'Second Instance' })
		.getByRole('button', { name: 'Set as default' }).click()

	// The Second Instance card should be marked as the default instance
	await expect(
		page.locator('.gateway-instance-card', { hasText: 'Second Instance' }),
	).toHaveClass(/(?:^|\s)gateway-instance-card--default(?:\s|$)/)

	await deleteGatewayInstance(request, adminUser, adminPassword, 'signal', first.id)
	await deleteGatewayInstance(request, adminUser, adminPassword, 'signal', second.id)
})
