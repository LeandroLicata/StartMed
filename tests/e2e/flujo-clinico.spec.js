import { test, expect } from '@playwright/test';

test.describe('StartMed - Flujos Críticos End-to-End', () => {

  test('1. Login de staff (Anestesista) y control de acceso 403', async ({ page }) => {
    await page.goto('/login');

    await page.fill('input[name="nombreUsuario"]', 'ramos');
    await page.fill('input[name="password"]', 'demo1234');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/anestesista/);
    await expect(page.locator('body')).toContainText('Ramos');

    const response = await page.goto('/admin/usuarios');
    expect(response.status()).toBe(403);
  });

  test('2. Portal del Paciente: acceso local y pestañas de cirugía', async ({ page }) => {
    await page.goto('/portal/entrar-local');
    await expect(page).toHaveURL(/\/portal/);
    await expect(page.locator('body')).toContainText(/García/i);

    const verCirugia = page.locator('a[href*="/portal/cirugias/"]').first();
    await expect(verCirugia).toBeVisible();
    await verCirugia.click();

    await expect(page.locator('body')).toContainText(/Resumen/i);
    await expect(page.locator('body')).toContainText(/Preanestésica/i);
  });

});
