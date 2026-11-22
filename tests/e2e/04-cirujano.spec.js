import { test, expect } from '@playwright/test';
import {
  loginStaff,
  leerMetrica,
  seleccionarOpcionPorTexto,
} from './helpers/auth.js';

test.describe('Rol: Cirujano (perez)', () => {
  test('1. Panel /cirujano: métricas propias, secciones del tablero y aislamiento respecto a otros cirujanos', async ({ page }) => {
    await test.step('Iniciar sesión como perez y verificar aterrizaje en /cirujano', async () => {
      await loginStaff(page, 'perez', 'demo1234');
      await expect(page).toHaveURL(/\/cirujano$/);
      await expect(page.getByRole('heading', { name: 'Mis cirugias', level: 1 })).toBeVisible();
      await expect(page.locator('header.sticky')).toContainText('Pérez, Daniel');
    });

    await test.step('Verificar los indicadores del panel del cirujano', async () => {
      expect(await leerMetrica(page, 'Cirugias programadas')).toBeGreaterThan(0);
      expect(await leerMetrica(page, 'Proximas con implante')).toBeGreaterThanOrEqual(1);
      await expect(page.locator('main')).toContainText('Suspension propia');
      await expect(page.locator('main')).toContainText('Con checklist 100% cerrado');
    });

    await test.step('Verificar que Mis proximas cirugias lista solo pacientes de perez y no los de lopez', async () => {
      const seccionProximas = page
        .locator('main section')
        .filter({ has: page.getByRole('heading', { name: 'Mis proximas cirugias' }) })
        .first();

      await expect(seccionProximas).toBeVisible();
      await expect(seccionProximas).toContainText('López, Ramiro');
      await expect(seccionProximas).toContainText('Ramírez, Luis');
      // Carla Fernández (Tiroidectomía) está asignada a la Dra. Silvia López
      await expect(seccionProximas).not.toContainText('Fernández, Carla');
    });

    await test.step('Verificar tarjetas Que falta resolver, Procedimientos habilitados e Historial de Cirugías', async () => {
      await expect(page.getByRole('heading', { name: 'Pacientes de hoy' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Que falta resolver' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Procedimientos habilitados' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Historial de Cirugías' })).toBeVisible();
    });
  });

  test('2. Agenda propia (/cirujano/agenda) e Historial completo (/cirujano/historial)', async ({ page }) => {
    await loginStaff(page, 'perez', 'demo1234');
    const nav = page.getByRole('navigation', { name: 'Secciones' });

    await test.step('Navegar a Agenda (/cirujano/agenda) en vista Mes y cambiar de período', async () => {
      await nav.getByRole('link', { name: 'Agenda' }).click();
      await expect(page).toHaveURL(/\/cirujano\/agenda$/);
      await expect(page.getByRole('heading', { name: 'Agenda', level: 1 })).toBeVisible();

      const mesActual = (await page.locator('main h2').first().innerText()).trim();
      await page.getByRole('link', { name: 'Mes siguiente' }).click();
      await expect(page).toHaveURL(/mes=\d{4}-\d{2}/);
      await expect(page.locator('main h2').first()).not.toHaveText(mesActual);

      await page.getByRole('link', { name: 'Hoy' }).click();
      await expect(page).toHaveURL(/\/cirujano\/agenda$/);
    });

    await test.step('Cambiar a vista Semana y volver a vista Mes', async () => {
      await page.getByRole('group', { name: 'Vista de la agenda' }).getByRole('link', { name: 'Semana' }).click();
      await expect(page).toHaveURL(/vista=semana/);

      await page.getByRole('link', { name: 'Semana siguiente' }).click();
      await expect(page).toHaveURL(/semana=\d{4}-\d{2}-\d{2}/);

      await page.getByRole('group', { name: 'Vista de la agenda' }).getByRole('link', { name: 'Mes' }).click();
      await expect(page).toHaveURL(/\/cirujano\/agenda$/);
    });

    await test.step('Abrir el detalle del día actual (/cirujano/agenda/YYYY-MM-DD) y comprobar que filtra por perez', async () => {
      const celdaHoy = page.locator('a.border-hu-dorado').first();
      await expect(celdaHoy).toBeVisible();
      await celdaHoy.click();

      await expect(page).toHaveURL(/\/cirujano\/agenda\/\d{4}-\d{2}-\d{2}$/);
      await expect(page.getByRole('heading', { name: 'Cirugias del dia' })).toBeVisible();
      await expect(page.locator('table tbody')).toContainText('López, Ramiro');
      await expect(page.locator('table tbody')).not.toContainText('Fernández, Carla');
    });

    await test.step('Navegar a Historial de cirugías (/cirujano/historial) y volver al panel', async () => {
      await nav.getByRole('link', { name: 'Historial de cirugías' }).click();
      await expect(page).toHaveURL(/\/cirujano\/historial$/);
      await expect(page.getByRole('heading', { name: 'Historial Completo de Cirugías', exact: true })).toBeVisible();
      await expect(page.locator('table tbody tr').first()).toBeVisible();

      await page.getByRole('link', { name: 'Volver al panel' }).click();
      await expect(page).toHaveURL(/\/cirujano$/);
    });
  });

  test('3. Expediente clínico: Preparación, Autorización, Requerimientos y ausencia de Reprogramar/Cancelar', async ({ page }) => {
    await loginStaff(page, 'perez', 'demo1234');
    await expect(page).toHaveURL(/\/cirujano$/);

    await test.step('Abrir el expediente de Ramiro López y verificar que no ofrece Reprogramar ni Cancelar Cirugía', async () => {
      const linkRamiro = page
        .locator('main section')
        .filter({ has: page.getByRole('heading', { name: 'Mis proximas cirugias' }) })
        .locator('a')
        .filter({ hasText: 'López, Ramiro' })
        .first();
      await expect(linkRamiro).toBeVisible();
      await linkRamiro.click();
      await expect(page).toHaveURL(/\/cirugias\/\d+$/);

      await expect(page.getByRole('button', { name: 'Reprogramar' })).toHaveCount(0);

      await page.locator('#btn-menu-cirugia').click();
      const menu = page.locator('#dropdown-menu-cirugia');
      await expect(menu).toBeVisible();
      await expect(menu.getByRole('button', { name: 'Activar Implante' })).toBeVisible();
      await expect(menu.getByRole('button', { name: 'Cancelar Cirugía' })).toHaveCount(0);

      // Activar requerimiento de implante desde el menú de tres puntos
      await menu.getByRole('button', { name: 'Activar Implante' }).click();
      await expect(page.locator('body')).toContainText('Con implante');
    });

    const obsPreparacion = 'Indicaciones de ayuno verificadas por Dr. Pérez (E2E).';

    await test.step('Actualizar las indicaciones en la solapa Preparación y verificar persistencia tras recargar', async () => {
      await page.getByRole('link', { name: 'Preparación' }).click();
      await expect(page).toHaveURL(/tab=preparacion/);

      await page.getByRole('button', { name: 'Editar preparación' }).click();
      const modalPrep = page.locator('#modal-preparacion');
      await expect(modalPrep).toBeVisible();

      await modalPrep.locator('textarea[name="observacionesPreparacionPaciente"]').fill(obsPreparacion);
      await modalPrep.getByRole('button', { name: 'Guardar preparación' }).click();

      await expect(page.locator('body')).toContainText('Indicaciones de preparación actualizadas.');

      await page.getByRole('link', { name: 'Preparación' }).click();
      await page.reload();
      await expect(page.locator('body')).toContainText(obsPreparacion);
    });

    await test.step('Actualizar estado en la solapa Autorización financiador y verificar persistencia', async () => {
      await page.getByRole('link', { name: 'Autorización financiador' }).click();
      await expect(page).toHaveURL(/tab=autorizacion/);

      await page.getByRole('button', { name: 'Actualizar estado' }).click();
      const modalAut = page.locator('#modal-estado-autorizacion');
      await expect(modalAut).toBeVisible();

      await seleccionarOpcionPorTexto(modalAut.locator('select[name="idEstadoAutCirugia"]'), /^En auditoría médica$/);
      await modalAut
        .locator('input[name="observacionesAutoCirugiaEstado"]')
        .fill('Informe quirúrgico enviado por Dr. Pérez (E2E).');
      await modalAut.getByRole('button', { name: 'Actualizar' }).click();

      await expect(page.locator('body')).toContainText('Estado de la autorización actualizado.');
      await page.reload();
      await expect(page.locator('body')).toContainText('En auditoría médica');
      await expect(page.locator('body')).toContainText('Informe quirúrgico enviado por Dr. Pérez (E2E).');
    });
  });

  test('4. Expediente clínico: Estudios, Profilaxis ATB, Evaluación en solo lectura y vista previa del portal', async ({ page }) => {
    await loginStaff(page, 'perez', 'demo1234');
    await page.goto('/cirugias/2'); // Ramiro López (cirujano: Pérez)

    await test.step('Agregar un estudio prequirúrgico en la solapa Estudios y comprobar persistencia', async () => {
      await page.getByRole('link', { name: 'Estudios prequirúrgicos' }).click();
      await expect(page).toHaveURL(/tab=estudios/);

      await page.getByRole('button', { name: 'Agregar estudio' }).click();
      const modalEstudio = page.locator('#modal-agregar-estudio');
      await expect(modalEstudio).toBeVisible();

      await seleccionarOpcionPorTexto(modalEstudio.locator('select[name="idTipoEstudio"]'), /Coagulograma/);
      await modalEstudio.getByRole('button', { name: 'Agregar' }).click();

      await expect(page.locator('body')).toContainText('Estudio agregado correctamente.');
      await page.reload();
      await expect(page.locator('body')).toContainText('Coagulograma');
    });

    await test.step('Agregar profilaxis antibiótica en la solapa Profilaxis ATB / SAMR y comprobar persistencia', async () => {
      await page.getByRole('link', { name: 'Profilaxis ATB / SAMR' }).click();
      await expect(page).toHaveURL(/tab=profilaxis/);

      await page.locator('button[title="Agregar profilaxis"]').click();
      const modalProf = page.locator('#modal-agregar-profilaxis');
      await expect(modalProf).toBeVisible();

      await seleccionarOpcionPorTexto(modalProf.locator('select[name="idProfilaxis"]'), /Clindamicina 600mg IV/);
      await seleccionarOpcionPorTexto(modalProf.locator('select[name="idProfilaxisRol"]'), /Complementaria/);
      await modalProf.locator('input[name="indicaciones"]').fill('Refuerzo intraoperatorio indicado en E2E.');
      await modalProf.getByRole('button', { name: 'Agregar' }).click();

      await expect(page.locator('body')).toContainText('Profilaxis agregada correctamente.');
      await page.reload();
      await expect(page.locator('body')).toContainText('Clindamicina 600mg IV');
      await expect(page.locator('body')).toContainText('Refuerzo intraoperatorio indicado en E2E.');
    });

    await test.step('Verificar que la solapa Evaluación anestésica está en modo solo lectura para el cirujano', async () => {
      await page.getByRole('link', { name: 'Evaluación anestésica' }).click();
      await expect(page).toHaveURL(/tab=evaluacion/);

      await expect(page.locator('select[name="idTipoAsa"]')).toHaveCount(0);
      await expect(page.locator('button[name="decision"]')).toHaveCount(0);
    });

    await test.step('Abrir «Ver como el paciente» (/cirugias/{id}/portal-paciente)', async () => {
      await page.getByRole('link', { name: 'Ver como el paciente' }).click();
      await expect(page).toHaveURL(/\/cirugias\/2\/portal-paciente$/);
      await expect(page.locator('body')).toContainText('Hernioplastia inguinal');
    });
  });

  test('5. Padrón /pacientes en modo consulta: búsqueda por DNI y ficha sin controles de edición ni coberturas', async ({ page }) => {
    await loginStaff(page, 'perez', 'demo1234');

    await test.step('Buscar a Ramiro López por DNI 31220145 en /pacientes y verificar ausencia de Nuevo paciente y Editar', async () => {
      await page.getByRole('navigation', { name: 'Secciones' }).getByRole('link', { name: 'Pacientes' }).click();
      await expect(page).toHaveURL(/\/pacientes$/);
      await expect(page.getByRole('heading', { name: 'Pacientes', exact: true })).toBeVisible();
      await expect(page.getByRole('link', { name: 'Nuevo paciente' })).toHaveCount(0);

      await page.locator('input[name="buscar"]').fill('31220145');
      await page.getByRole('button', { name: 'Buscar' }).click();

      const tarjetaPaciente = page
        .locator('main section section')
        .filter({ hasText: 'López, Ramiro' })
        .first();
      await expect(tarjetaPaciente).toBeVisible();
      await expect(tarjetaPaciente.getByRole('link', { name: 'Editar' })).toHaveCount(0);
      await tarjetaPaciente.getByRole('link', { name: 'Ver historial' }).click();
    });

    await test.step('Comprobar en /pacientes/{id} que los datos personales y obras sociales son de solo lectura', async () => {
      await expect(page).toHaveURL(/\/pacientes\/\d+$/);
      await expect(page.locator('body')).toContainText('López, Ramiro');
      await expect(page.getByRole('link', { name: 'Editar datos' })).toHaveCount(0);
      await expect(page.locator('form[action*="/obras-sociales"]')).toHaveCount(0);
    });
  });

  test('6. Propiedad de cirugía (403 en detalle de otro cirujano), permisos 403 en rutas ajenas y Logout', async ({ page }) => {
    await loginStaff(page, 'perez', 'demo1234');
    await expect(page).toHaveURL(/\/cirujano$/);

    await test.step('Comprobar HTTP 403 en /cirujano/cirugias/3 (cirugía de Carla Fernández asignada a Dra. López)', async () => {
      const respAjena = await page.goto('/cirujano/cirugias/3');
      expect(respAjena.status()).toBe(403);
    });

    await test.step('Comprobar HTTP 403 en paneles de Gestor, Anestesista, Dirección y Administración', async () => {
      const rutasProhibidas = [
        '/dashboard',
        '/cirugias',
        '/cirugias/nueva',
        '/agenda',
        '/anestesista',
        '/anestesista/1/evaluar',
        '/direccion',
        '/pacientes/nuevo',
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

    await test.step('Cerrar sesión con el botón Salir y verificar redirección a /login al volver a /cirujano', async () => {
      await page.goto('/cirujano');
      await page.locator('header.sticky').getByRole('button', { name: 'Salir' }).click();
      await expect(page).toHaveURL(/\/login$/);

      await page.goto('/cirujano');
      await expect(page).toHaveURL(/\/login$/);
    });
  });

  // Bug detectado en análisis QA: CirujanoController::detalle() valida propiedad
  // pero devuelve view('paneles.cirujano-detalle'), archivo que no existe en resources/views/paneles/.
  test.fixme('7. [Bug conocido] /cirujano/cirugias/{idPropio} debería renderizar 200 sin lanzar 500 por vista faltante paneles.cirujano-detalle', async ({ page }) => {
    await loginStaff(page, 'perez', 'demo1234');
    const resp = await page.goto('/cirujano/cirugias/1');
    expect(resp.status()).toBe(200);
  });

  // Brecha de permisos detectada en análisis QA: en /cirugias/{id}, CirugiaController
  // valida el rol 'Cirujano' por middleware pero no verifica que $cirugia->idPersonalCirujano
  // coincida con el cirujano logueado (a diferencia de AnestesistaController::autorizarCirugia).
  test.fixme('8. [Bug conocido] Un cirujano no debería poder editar la preparación de una cirugía asignada a otro cirujano (/cirugias/3 de lopez)', async ({ page }) => {
    await loginStaff(page, 'perez', 'demo1234');
    await page.goto('/cirugias/3?tab=preparacion');
    await expect(page.getByRole('button', { name: 'Editar preparación' })).toHaveCount(0);
  });
});
