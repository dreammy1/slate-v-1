const { test, expect } = require('@playwright/test');

test.describe('Slate authentication surfaces', () => {
  test('renders the admin login form', async ({ page }) => {
    await page.goto('/admin/login.php');

    await expect(page).toHaveTitle(/Log in/i);
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
    await expect(page.locator('form')).toHaveCount(1);
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Log in' })).toBeVisible();
  });

  test('toggles the admin password field visibility', async ({ page }) => {
    await page.goto('/admin/login.php');

    const password = page.locator('#password');
    await expect(password).toHaveAttribute('type', 'password');
    await page.getByRole('button', { name: /show password/i }).click();
    await expect(password).toHaveAttribute('type', 'text');
  });

  test('renders the customer login form and recovery link', async ({ page }) => {
    await page.goto('/customer/login.php');

    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Forgot password?' })).toHaveAttribute(
      'href',
      /customer\/forgot-password\.php/
    );
  });
});

test.describe('Slate security boundaries', () => {
  test('does not expose internal database files over HTTP', async ({ request }) => {
    const response = await request.get('/db/schema.sql');
    expect([403, 404]).toContain(response.status());
  });

  test('returns a branded response for an unknown direct page', async ({ request }) => {
    const response = await request.get('/does-not-exist.php');
    expect([403, 404]).toContain(response.status());
  });
});
