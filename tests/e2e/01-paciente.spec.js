import { test, expect } from '@playwright/test';
import {
  loginStaff,
  loginPacienteLocal,
  crearCirugiaDesdeUI,
} from './helpers/auth.js';

// PNG válido de 1x1 px en memoria para probar la subida real de resultados de estudios
const PNG_PRUEBA = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
  'base64',
);

const PESTANAS_ESPERADAS = [
  'Resumen',
  'Estudios',
  'Preanestésica',
  'Preparación',
  'Consentimiento',
  'Turnos',
  'Contacto',
];

test.describe('Rol: Paciente (/portal)', () => {
  test('1. Ingreso al portal: redirección sin sesión, solicitud por DNI y acceso local', async ({ page }) => {
    await test.step('Redirigir a /portal/ingresar si el paciente no está autenticado', async () => {
      await page.goto('/portal');
      await expect(page).toHaveURL(/\/portal\/ingresar$/);
      await expect(page.getByRole('heading', { name: 'Portal del paciente' })).toBeVisible();
    });

    await test.step('Solicitar enlace de ingreso por DNI 28456789', async () => {
      await page.locator('input[name="documento"]').fill('28456789');
      await page.getByRole('button', { name: 'Enviar enlace' }).click();

      await expect(page).toHaveURL(/\/portal\/ingresar$/);
      await expect(page.locator('body')).toContainText(
        'Si el DNI corresponde a un paciente con un correo registrado, te enviamos un enlace para ingresar.',
      );
    });

    await test.step('Ingresar al portal mediante atajo local (/portal/entrar-local)', async () => {
      await page.getByRole('link', { name: /Entrar como María García/ }).click();

      await expect(page).toHaveURL(/\/portal$/);
      await expect(page.getByRole('heading', { name: 'Próximas cirugías' })).toBeVisible();
      await expect(page.locator('body > header')).toContainText('García, María');
    });
  });

  test('2. Listado y Resumen de cirugía: muestra las 7 pestañas del expediente', async ({ page }) => {
    await test.step('Iniciar sesión en el portal como María García', async () => {
      await loginPacienteLocal(page);
      await expect(page.getByRole('heading', { name: 'Próximas cirugías' })).toBeVisible();
    });

    await test.step('Abrir la cirugía activa (Hernioplastia inguinal)', async () => {
      const tarjetaCirugia = page
        .locator('a[href*="/portal/cirugias/"]')
        .filter({ hasText: 'Hernioplastia inguinal' })
        .first();
      await expect(tarjetaCirugia).toBeVisible();
      await tarjetaCirugia.click();

      await expect(page).toHaveURL(/\/portal\/cirugias\/\d+$/);
      await expect(page.getByRole('heading', { name: 'Hernioplastia inguinal' })).toBeVisible();
    });

    await test.step('Verificar las 7 pestañas y el contenido de Resumen', async () => {
      const navSecciones = page.getByRole('navigation', { name: 'Secciones de la cirugía' });
      for (const pestana of PESTANAS_ESPERADAS) {
        await expect(navSecciones.getByRole('link', { name: pestana })).toBeVisible();
      }

      await expect(page.locator('body')).toContainText('Datos de mi cirugía');
      await expect(page.locator('body')).toContainText('Estado de mi proceso');
      await expect(page.locator('body')).toContainText('OSDE 410');
    });
  });

  test('3. Estudios y Preparación: consulta de ayuno y subida persistente de resultado', async ({ page }) => {
    await test.step('Entrar a la cirugía activa con estudio pendiente', async () => {
      await loginPacienteLocal(page);
      await page
        .locator('a[href*="/portal/cirugias/"]')
        .filter({ hasText: 'Hernioplastia inguinal' })
        .first()
        .click();
      await expect(page).toHaveURL(/\/portal\/cirugias\/\d+$/);
    });

    const navSecciones = page.getByRole('navigation', { name: 'Secciones de la cirugía' });

    await test.step('Verificar indicaciones de preparación y ayuno', async () => {
      await navSecciones.getByRole('link', { name: 'Preparación' }).click();
      await expect(page).toHaveURL(/\/preparacion$/);

      await expect(page.locator('body')).toContainText('Indicaciones de preparación');
      await expect(page.locator('body')).toContainText('Ayuno');
      await expect(page.locator('body')).toContainText('Sólidos');
      await expect(page.locator('body')).toContainText('Líquidos claros');
      await expect(page.locator('body')).toContainText('Última ingesta');
      await expect(page.locator('body')).toContainText('Higiene prequirúrgica');
    });

    await test.step('Subir resultado en el estudio pendiente (Electrocardiograma)', async () => {
      await navSecciones.getByRole('link', { name: 'Estudios' }).click();
      await expect(page).toHaveURL(/\/estudios$/);

      const itemPendiente = page
        .locator('main ul li')
        .filter({ hasText: 'Electrocardiograma' })
        .first();

      await expect(itemPendiente).toBeVisible();
      await expect(itemPendiente).toContainText('Pendiente');

      await itemPendiente.locator('input[name="archivoResultadoEstudio"]').setInputFiles({
        name: 'ecg-resultado-e2e.png',
        mimeType: 'image/png',
        buffer: PNG_PRUEBA,
      });

      await itemPendiente.getByRole('button', { name: 'Subir' }).click();

      await expect(page.locator('body')).toContainText('Listo, recibimos tu estudio.');
      await expect(itemPendiente).toContainText('Subido');
      await expect(itemPendiente.getByRole('link', { name: 'Ver el resultado' })).toBeVisible();
    });

    await test.step('Recargar la página y confirmar persistencia del estudio subido', async () => {
      await page.reload();

      const itemRecargado = page
        .locator('main ul li')
        .filter({ hasText: 'Electrocardiograma' })
        .first();

      await expect(itemRecargado).toContainText('Subido');
      await expect(itemRecargado.getByRole('link', { name: 'Ver el resultado' })).toBeVisible();
      await expect(itemRecargado.locator('input[name="archivoResultadoEstudio"]')).toHaveCount(0);
    });
  });

  test('4. Cuestionario Preanestésico y Consentimiento sobre cirugía limpia', async ({ browser, page }) => {
    test.slow();
    let cirugiaId;

    await test.step('Crear cirugía limpia para María García (sin cuestionario ni firma previos)', async () => {
      const ctxGestor = await browser.newContext();
      try {
        const pageGestor = await ctxGestor.newPage();
        await loginStaff(pageGestor, 'gonzalez', 'demo1234');
        await expect(pageGestor).toHaveURL(/\/dashboard/);

        const fechaBase = await pageGestor.locator('input[name="hasta"]').inputValue();
        const [anio, mes, dia] = fechaBase.split('-').map(Number);
        const fechaFutura = new Date(Date.UTC(anio, mes - 1, dia + 5));
        const yyyy = fechaFutura.getUTCFullYear();
        const mm = String(fechaFutura.getUTCMonth() + 1).padStart(2, '0');
        const dd = String(fechaFutura.getUTCDate()).padStart(2, '0');

        cirugiaId = await crearCirugiaDesdeUI(pageGestor, {
          dniPaciente: '28456789',
          tipoCirugia: /Tiroidectomía/,
          quirofano: /Quirófano de urgencias/,
          fechaHoraInicio: `${yyyy}-${mm}-${dd}T15:00`,
          cirujano: /López/,
          anestesista: null,
          cobertura: 'existente',
        });
      } finally {
        await ctxGestor.close();
      }
    });

    await test.step('Ingresar al portal y abrir la cirugía recién creada', async () => {
      await loginPacienteLocal(page);
      const enlaceCirugia = page.locator(`a[href$="/portal/cirugias/${cirugiaId}"]`);
      await expect(enlaceCirugia).toBeVisible();
      await enlaceCirugia.click();
    });

    const navSecciones = page.getByRole('navigation', { name: 'Secciones de la cirugía' });

    await test.step('Responder el cuestionario preanestésico y verificar que queda en solo lectura', async () => {
      await navSecciones.getByRole('link', { name: 'Preanestésica' }).click();
      await expect(page).toHaveURL(new RegExp(`/portal/cirugias/${cirugiaId}/preanestesica$`));
      await expect(page.locator('body')).toContainText('Pendiente');

      const fieldsets = page.locator('main form fieldset');
      const totalCerradas = await fieldsets.count();
      expect(totalCerradas).toBeGreaterThan(0);
      for (let i = 0; i < totalCerradas; i++) {
        await fieldsets.nth(i).locator('input[type="radio"]').first().check();
      }

      const respuestaAbierta = 'Levotiroxina 50mcg diaria (E2E paciente)';
      const inputsAbiertos = page.locator('main form input[type="text"]');
      const totalAbiertas = await inputsAbiertos.count();
      expect(totalAbiertas).toBeGreaterThan(0);
      for (let i = 0; i < totalAbiertas; i++) {
        await inputsAbiertos.nth(i).fill(i === 0 ? respuestaAbierta : 'Hipotiroidismo controlado');
      }

      await page.getByRole('button', { name: 'Enviar respuestas' }).click();

      await expect(page.locator('body')).toContainText('Listo, recibimos tus respuestas.');
      await expect(page.locator('body')).toContainText('Respondido');
      await expect(page.locator('body')).toContainText(respuestaAbierta);
      await expect(page.locator('main form')).toHaveCount(0);

      // Confirmar persistencia y solo lectura tras recargar
      await page.reload();
      await expect(page.locator('body')).toContainText('Respondido');
      await expect(page.locator('body')).toContainText(respuestaAbierta);
      await expect(page.locator('main form')).toHaveCount(0);
    });

    await test.step('Firmar el consentimiento informado pasando por #dialogo-consentimiento y #dialogo-confirmar', async () => {
      await navSecciones.getByRole('link', { name: 'Consentimiento' }).click();
      await expect(page).toHaveURL(new RegExp(`/portal/cirugias/${cirugiaId}/consentimiento$`));
      await expect(page.locator('body')).toContainText('Sin firmar');

      await page.locator('button[data-abrir-consentimiento]').click();

      const dialogoConsentimiento = page.locator('#dialogo-consentimiento');
      await expect(dialogoConsentimiento).toBeVisible();

      const btnFirmar = dialogoConsentimiento.locator('[data-firmar-consentimiento]');
      await expect(btnFirmar).toBeDisabled();

      await dialogoConsentimiento.locator('[data-casilla-consentimiento]').check();
      await expect(btnFirmar).toBeEnabled();
      await btnFirmar.click();

      const dialogoConfirmar = page.locator('#dialogo-confirmar');
      await expect(dialogoConfirmar).toBeVisible();
      await dialogoConfirmar.locator('[data-dialogo-aceptar]').click();

      await expect(page.locator('body')).toContainText('Listo, firmaste el consentimiento informado.');
      await expect(page.locator('body')).toContainText('Firmado');

      // Confirmar persistencia tras recargar
      await page.reload();
      await expect(page.locator('body')).toContainText('Firmaste el consentimiento informado');
      await expect(page.getByRole('button', { name: 'Ver el documento firmado' })).toBeVisible();
    });
  });

  test('5. Perfil y Contacto: actualización de teléfonos y envío de mensaje al equipo', async ({ page }) => {
    await test.step('Iniciar sesión en el portal y abrir Mi perfil', async () => {
      await loginPacienteLocal(page);
      await page.getByRole('navigation', { name: 'Secciones del portal' }).getByRole('link', { name: 'Perfil' }).click();
      await expect(page).toHaveURL(/\/portal\/perfil$/);
      await expect(page.locator('body')).toContainText('28456789');
    });

    await test.step('Actualizar teléfonos de contacto y comprobar persistencia tras recargar', async () => {
      await page.locator('input[name="telefono_codigo"]').fill('261');
      await page.locator('input[name="telefono_numero"]').fill('4259988');
      await page.locator('input[name="contacto_celular_codigo"]').fill('261');
      await page.locator('input[name="contacto_celular_numero"]').fill('5558899');

      await page.getByRole('button', { name: 'Guardar cambios' }).click();

      await expect(page).toHaveURL(/\/portal\/perfil$/);
      await expect(page.locator('body')).toContainText('Tus datos quedaron guardados.');

      await page.reload();
      await expect(page.locator('input[name="telefono_codigo"]')).toHaveValue('261');
      await expect(page.locator('input[name="telefono_numero"]')).toHaveValue('4259988');
      await expect(page.locator('input[name="contacto_celular_codigo"]')).toHaveValue('261');
      await expect(page.locator('input[name="contacto_celular_numero"]')).toHaveValue('5558899');
    });

    await test.step('Enviar un mensaje al equipo desde la pestaña Contacto', async () => {
      await page.goto('/portal');
      await page
        .locator('a[href*="/portal/cirugias/"]')
        .filter({ hasText: 'Hernioplastia inguinal' })
        .first()
        .click();

      await page
        .getByRole('navigation', { name: 'Secciones de la cirugía' })
        .getByRole('link', { name: 'Contacto' })
        .click();
      await expect(page).toHaveURL(/\/contacto$/);

      await page
        .locator('textarea[name="mensaje"]')
        .fill('Hola equipo, necesito confirmar si debo llevar estudios impresos el día de la cirugía.');

      await page.getByRole('button', { name: 'Enviar mensaje' }).click();

      await expect(page.locator('body')).toContainText(
        'Listo, le mandamos tu mensaje al equipo. Te van a responder por teléfono o email.',
      );
    });
  });

  test('6. Aislamiento de sesión: el paciente no puede acceder a rutas del staff', async ({ page }) => {
    await test.step('Iniciar sesión únicamente en el guard del paciente', async () => {
      await loginPacienteLocal(page);
      await expect(page).toHaveURL(/\/portal$/);
    });

    await test.step('Intentar entrar a rutas del staff (/dashboard, /anestesista, /admin) y verificar redirección a /login', async () => {
      for (const rutaStaff of ['/dashboard', '/anestesista', '/admin']) {
        await page.goto(rutaStaff);
        await expect(page).toHaveURL(/\/login$/);
      }
    });

    await test.step('Confirmar que la sesión del portal sigue activa tras los bloqueos', async () => {
      await page.goto('/portal');
      await expect(page).toHaveURL(/\/portal$/);
      await expect(page.getByRole('heading', { name: 'Próximas cirugías' })).toBeVisible();
    });

    await test.step('Cerrar sesión del portal y verificar que /portal vuelve a exigir ingreso', async () => {
      await page.getByRole('button', { name: 'Salir' }).click();
      await expect(page).toHaveURL(/\/portal\/ingresar$/);

      await page.goto('/portal');
      await expect(page).toHaveURL(/\/portal\/ingresar$/);
    });
  });
});
