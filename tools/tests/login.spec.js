/* eslint-env node */
const { test, expect } = require('@playwright/test');

const BASE = 'http://localhost/Sakorms.org/Inventory-web1.5/login.html';

test.beforeEach(async ({ page }) => {
  // Prefer a clean page load
  await page.goto(BASE, { waitUntil: 'load' });
});

test('Login page - basic interactive controls', async ({ page }) => {
  // Close welcome dialog if present
  const closeWelcome = page.locator('#close-welcome');
  if (await closeWelcome.count()) await closeWelcome.click();

  // Inputs exist
  await expect(page.locator('#email')).toBeVisible();
  await expect(page.locator('#password')).toBeVisible();

  // Toggle password visibility
  const toggle = page.locator('#toggle-password');
  await toggle.click();
  await expect(page.locator('#password')).toHaveAttribute('type', 'text');
  await toggle.click();
  await expect(page.locator('#password')).toHaveAttribute('type', 'password');

  // Access key help toggles hint
  await page.locator('#access-key-help').click();
  await expect(page.locator('#access-key-hint')).toBeVisible();

  // FABs: calculator, AI chat and AI enhancement
  // Ensure welcome dialog is closed (if present) so FABs are clickable
  const closeWelcomeBtn = page.locator('#close-welcome');
  if (await closeWelcomeBtn.count()) {
    try { await closeWelcomeBtn.click({ timeout: 2000 }); } catch(e) { /* ignore */ }
  }

  // FABs: calculator, AI chat and AI enhancement — wait for visibility then click
  await page.locator('#btnCalc').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('#btnCalc').click({ timeout: 3000 });
  await expect(page.locator('#calculatorModal')).toHaveClass(/active/);

  await page.locator('#btnAIChat').waitFor({ state: 'visible', timeout: 3000 });
  await page.locator('#btnAIChat').click({ timeout: 3000 });
  await expect(page.locator('#aiChatbot')).toHaveClass(/active/);

  await page.locator('#btnAIEnhance').waitFor({ state: 'visible', timeout: 3000 });
  await page.locator('#btnAIEnhance').click({ timeout: 3000 });
  // ai-enhanced class toggled on body
  await page.waitForFunction(() => document.body.classList.contains('ai-enhanced'), null, { timeout: 2000 });

  // Data-onclick links: show register and recover
  await page.locator('a[data-onclick*="mostrarRegistro"]').click();
  await expect(page.locator('#register-form')).toBeVisible();

  await page.locator('a[data-onclick*="mostrarLogin"]').click();
  await expect(page.locator('#login-form')).toBeVisible();
});

test('Login form submission triggers redirect on success', async ({ page }) => {
  // Spy redirectTo
  await page.evaluate(() => {
    window.__redirectCalled = false;
    const _orig = window.redirectTo;
    window.redirectTo = function(page) { window.__redirectCalled = page || true; };
  });

  // Stub fetch to return a successful JSON response
  await page.route('**/api/login.php', route => {
    route.fulfill({
      status: 200,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ success: true })
    });
  });

  await page.fill('#email', 'test@example.com');
  await page.fill('#password', 'password123');
  await page.locator('#login-form').evaluate(form => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));

  // Wait for redirectTo to be called (login.js calls redirect after 1s)
  await page.waitForFunction(() => window.__redirectCalled === true || typeof window.__redirectCalled === 'string', null, { timeout: 3000 });
  const called = await page.evaluate(() => window.__redirectCalled);
  expect(called).toBeTruthy();
});
