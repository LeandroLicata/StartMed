import { test, expect } from '@playwright/test';
import {
  loginStaff,
  leerMetrica,
} from './helpers/auth.js';

let usuarioDireccionCreado = false;

async function asegurarUsuarioDireccion(browser) {
  if (usuarioDireccionCreado) {
    return;
  }

  const ctxAdmin = await browser.newContext();
  try {
    const pageAdmin = await ctxAdmin.newPage();
    await loginStaff(pageAdmin, 'admin', 'admin1234');
    await pageAdmin.goto('/admin/usuarios?q=direccion');
    const yaExiste = await pageAdmin.locator('table tbody tr').filter({ hasText: 'direccion' }).count();
    if (yaExiste > 0) {
      usuarioDireccionCreado = true;
      return;
    }

    await pageAdmin.goto('/admin/usuarios/nuevo');
    await expect(pageAdmin.getByRole('heading', { name: 'Nuevo usuario', level: 1 })).toBeVisible();

    await pageAdmin.locator('input[name="documento"]').fill('24998877');
    await pageAdmin.locator('input[name="apellidos"]').fill('Navarro');
    await pageAdmin.locator('input[name="nombres"]').fill('Elena');
    await pageAdmin.locator('input[name="contacto_email_direccion"]').fill('elena.navarro@hospital.uncuyo.edu.ar');
    await pageAdmin.locator('input[name="telefono_numero"]').fill('2614778899');
    await pageAdmin.locator('input[name="direccionPersona"]').fill('Av. Emilio Civit 450, Mendoza');
    await pageAdmin.locator('input[name="legajoPersonal"]').fill('0099');
    await pageAdmin.locator('input[name="nombreUsuario"]').fill('direccion');
    await pageAdmin.locator('input[name="password"]').fill('demo1234');
    await pageAdmin.locator('input[name="password_confirmation"]').fill('demo1234');

    await pageAdmin
      .locator('label')
      .filter({ hasText: 'Dirección médica' })
      .locator('input[type="checkbox"][name="roles[]"]')
      .check();

    await pageAdmin.getByRole('button', { name: 'Crear usuario' }).click();
    await expect(pageAdmin).toHaveURL(/\/admin\/usuarios$/);
    await expect(pageAdmin.locator('body')).toContainText('Usuario direccion creado correctamente.');

    usuarioDireccionCreado = true;
  } finally {
    await ctxAdmin.close();
  }
}

test.describe('Rol: Dirección médica (direccion)', () => {
  test('1. Setup de usuario Dirección médica, aterrizaje en /dashboard y menú lateral exclusivo', async ({ browser, page }) => {
    await test.step('Dar de alta al usuario direccion con rol Dirección médica desde /admin/usuarios/nuevo', async () => {
      await asegurarUsuarioDireccion(browser);
    });

    await test.step('Iniciar sesión como direccion y verificar aterrizaje automático en /dashboard', async () => {
      await loginStaff(page, 'direccion', 'demo1234');
      await expect(page).toHaveURL(/\/dashboard$/);
      await expect(page.getByRole('heading', { name: 'Tablero de quirófano', level: 1 })).toBeVisible();
      await expect(page.locator('header.sticky')).toContainText('Navarro, Elena');
    });

    await test.step('Verificar que el menú lateral muestra únicamente Tablero y Dirección', async () => {
      const nav = page.getByRole('navigation', { name: 'Secciones' });
      const enlaces = nav.getByRole('link');
      await expect(enlaces).toHaveCount(2);
      await expect(nav.getByRole('link', { name: 'Tablero' })).toBeVisible();
      await expect(nav.getByRole('link', { name: 'Dirección' })).toBeVisible();
    });
  });

  test('2. Panel ejecutivo /direccion: indicadores de 6 meses, gráficos y alerta informativa', async ({ browser, page }) => {
    await asegurarUsuarioDireccion(browser);
    await loginStaff(page, 'direccion', 'demo1234');

    await test.step('Navegar a /direccion desde el menú lateral y verificar encabezado del período', async () => {
      await page.getByRole('navigation', { name: 'Secciones' }).getByRole('link', { name: 'Dirección' }).click();
      await expect(page).toHaveURL(/\/direccion$/);
      await expect(page.getByRole('heading', { name: 'Dirección médica', level: 1 })).toBeVisible();
      await expect(page.locator('header.sticky')).toContainText('Período');
    });

    await test.step('Comprobar las 4 métricas ejecutivas del período de 6 meses', async () => {
      expect(await leerMetrica(page, 'Cirugías este mes')).toBeGreaterThan(0);
      expect(await leerMetrica(page, 'Total del período')).toBeGreaterThanOrEqual(200);
      expect(await leerMetrica(page, 'Cirugías suspendidas')).toBeGreaterThanOrEqual(14);
      await expect(page.locator('main')).toContainText('Tasa de suspensión');
      await expect(page.locator('main')).toContainText('Objetivo institucional: menos del 5%');
    });

    await test.step('Verificar tarjetas Volumen mensual, Suspensión por mes y Uso de quirófanos', async () => {
      await expect(page.getByRole('heading', { name: 'Volumen mensual' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Suspensión por mes' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Uso de quirófanos' })).toBeVisible();

      const tarjetaQuirofanos = page
        .locator('main section')
        .filter({ has: page.getByRole('heading', { name: 'Uso de quirófanos' }) })
        .first();
      await expect(tarjetaQuirofanos).toContainText('Quirófano central');
      await expect(tarjetaQuirofanos).toContainText('Q1');
      await expect(tarjetaQuirofanos).toContainText('Q2');
      await expect(tarjetaQuirofanos).toContainText('Q3');
      await expect(tarjetaQuirofanos).toContainText('Q4');
    });

    await test.step('Verificar alerta explicativa «Qué falta para completar este tablero»', async () => {
      await expect(page.locator('main')).toContainText('Qué falta para completar este tablero');
      await expect(page.locator('main')).toContainText('CirugiaEstado');
      await expect(page.locator('main')).toContainText('PedidoMaterial.subtotalPedidoMaterial');
    });
  });

  test('3. Tablero /dashboard y consulta de expediente /cirugias/{id} en modo solo lectura', async ({ browser, page }) => {
    await asegurarUsuarioDireccion(browser);
    await loginStaff(page, 'direccion', 'demo1234');
    await expect(page).toHaveURL(/\/dashboard$/);

    await test.step('Filtrar Cirugías de la semana en /dashboard y abrir un expediente', async () => {
      await page.locator('input[name="q"]').fill('García');
      await page.getByRole('button', { name: 'Filtrar' }).click();
      await expect(page.locator('table tbody')).toContainText('García, María');

      await page.locator('table tbody tr').first().locator('a').first().click();
      await expect(page).toHaveURL(/\/cirugias\/\d+$/);
    });

    await test.step('Comprobar ausencia de botones de escritura en cabecera y en todas las solapas del expediente', async () => {
      // Sin Reprogramar ni menú de tres puntos en cabecera
      await expect(page.getByRole('button', { name: 'Reprogramar' })).toHaveCount(0);
      await expect(page.locator('#btn-menu-cirugia')).toHaveCount(0);

      // Solapa Preparación: sin modal ni botón visible de edición
      await page.getByRole('link', { name: 'Preparación' }).click();
      await expect(page).toHaveURL(/tab=preparacion/);
      await expect(page.locator('#modal-preparacion')).toHaveCount(0);
      await expect(page.getByRole('button', { name: 'Editar preparación' })).toBeHidden();

      // Solapa Estudios: sin botón Agregar estudio
      await page.getByRole('link', { name: 'Estudios prequirúrgicos' }).click();
      await expect(page).toHaveURL(/tab=estudios/);
      await expect(page.getByRole('button', { name: 'Agregar estudio' })).toHaveCount(0);

      // Solapa Autorización: sin botón Actualizar estado
      await page.getByRole('link', { name: 'Autorización financiador' }).click();
      await expect(page).toHaveURL(/tab=autorizacion/);
      await expect(page.getByRole('button', { name: 'Actualizar estado' })).toHaveCount(0);

      // Solapa Profilaxis: sin botones de edición ni alta
      await page.getByRole('link', { name: 'Profilaxis ATB / SAMR' }).click();
      await expect(page).toHaveURL(/tab=profilaxis/);
      await expect(page.locator('button[title="Editar datos del hisopado"]')).toHaveCount(0);
      await expect(page.locator('button[title="Agregar profilaxis"]')).toHaveCount(0);

      // Solapa Evaluación anestésica: solo lectura
      await page.getByRole('link', { name: 'Evaluación anestésica' }).click();
      await expect(page).toHaveURL(/tab=evaluacion/);
      await expect(page.locator('select[name="idTipoAsa"]')).toHaveCount(0);
      await expect(page.locator('button[name="decision"]')).toHaveCount(0);
    });

    await test.step('Abrir «Ver como el paciente» (/cirugias/{id}/portal-paciente)', async () => {
      await page.getByRole('link', { name: 'Ver como el paciente' }).click();
      await expect(page).toHaveURL(/\/cirugias\/\d+\/portal-paciente$/);
      await expect(page.locator('body')).toContainText('García, María');
    });
  });

  test('4. Permisos 403 en rutas ajenas y cierre de sesión (Logout)', async ({ browser, page }) => {
    await asegurarUsuarioDireccion(browser);
    await loginStaff(page, 'direccion', 'demo1234');
    await expect(page).toHaveURL(/\/dashboard$/);

    await test.step('Comprobar HTTP 403 en rutas de Gestor, Padrón de pacientes, Cirujano, Anestesista y Administración', async () => {
      const rutasProhibidas = [
        '/cirugias',
        '/cirugias/nueva',
        '/agenda',
        '/pacientes',
        '/pacientes/1',
        '/pacientes/nuevo',
        '/cirujano',
        '/cirujano/agenda',
        '/cirujano/historial',
        '/anestesista',
        '/anestesista/1/evaluar',
        '/admin',
        '/admin/usuarios',
        '/admin/cuestionario',
        '/admin/auditoria',
      ];

      for (const ruta of rutasProhibidas) {
        const resp = await page.goto(ruta);
        expect(resp.status(), `Se esperaba 403 en ${ruta}`).toBe(403);
      }
    });

    await test.step('Cerrar sesión con el botón Salir y verificar protección de /direccion y /dashboard', async () => {
      await page.goto('/direccion');
      await page.locator('header.sticky').getByRole('button', { name: 'Salir' }).click();
      await expect(page).toHaveURL(/\/login$/);

      await page.goto('/direccion');
      await expect(page).toHaveURL(/\/login$/);
    });
  });

  // Bug detectado en análisis QA: en resources/views/cirugias/show.blade.php (línea 339),
  // la tarjeta Equipo quirúrgico muestra los botones de reasignar Cirujano/Anestesista a
  // 'Gestor de quirófano' y 'Dirección médica', pero en routes/web.php (línea 157) la ruta
  // cirugias.personal.disponible tiene middleware 'rol:Gestor de quirófano,Cirujano' y responde 403.
  test.fixme('5. [Bug conocido] Los botones de reasignar en Equipo quirúrgico visibles para Dirección médica no deberían fallar con 403 en /api/personal-disponible', async ({ browser, page }) => {
    await asegurarUsuarioDireccion(browser);
    await loginStaff(page, 'direccion', 'demo1234');
    await page.goto('/cirugias/1');

    const tarjetaEquipo = page
      .locator('main section')
      .filter({ has: page.getByRole('heading', { name: 'Equipo quirúrgico' }) })
      .first();

    const [respDisponible] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/personal-disponible')),
      tarjetaEquipo.getByRole('button', { name: 'Cirujano' }).click(),
    ]);

    expect(respDisponible.status()).toBe(200);
  });
});
