import { test, expect } from '@playwright/test';
import {
  loginStaff,
  loginPacienteLocal,
  leerMetrica,
  crearCirugiaDesdeUI,
} from './helpers/auth.js';

test.describe('Flujo cruzado Paciente → Anestesista', () => {
  test('paciente responde cuestionario preanestésico y firma consentimiento; anestesista lo ve reflejado en su panel', async ({ browser }) => {
    // Preparación vía UI con gestor: crear una cirugía limpia para María García (DNI 28456789)
    // asignada a Ramos, ya que ExpedienteSeeder pre-responde el cuestionario de las cirugías demo.
    const ctxGestor = await browser.newContext();
    let cirugiaId;
    let fechaLibreISO;
    try {
      const pageGestor = await ctxGestor.newPage();
      await loginStaff(pageGestor, 'gonzalez', 'demo1234');
      await expect(pageGestor).toHaveURL(/\/dashboard/);

      // Usar 3 días después de "hasta" (o +3 días desde hoy): fecha segura sin superposiciones
      const fechaBase = await pageGestor.locator('input[name="hasta"]').inputValue();
      const [anio, mes, dia] = fechaBase.split('-').map(Number);
      const fechaFutura = new Date(Date.UTC(anio, mes - 1, dia + 3));
      const yyyy = fechaFutura.getUTCFullYear();
      const mm = String(fechaFutura.getUTCMonth() + 1).padStart(2, '0');
      const dd = String(fechaFutura.getUTCDate()).padStart(2, '0');
      fechaLibreISO = `${yyyy}-${mm}-${dd}T16:00`;

      cirugiaId = await crearCirugiaDesdeUI(pageGestor, {
        dniPaciente: '28456789', // María García (paciente demo de /portal/entrar-local)
        tipoCirugia: /Tiroidectomía/,
        quirofano: /Quirófano de urgencias/,
        fechaHoraInicio: fechaLibreISO,
        cirujano: /López/,
        anestesista: /Ramos/,
        cobertura: 'existente',
      });
    } finally {
      await ctxGestor.close();
    }

    // Dos contextos completamente aislados: Paciente (/portal) y Anestesista (/anestesista)
    const contextPaciente = await browser.newContext();
    const contextAnestesista = await browser.newContext();

    try {
      const pagePaciente = await contextPaciente.newPage();
      const pageAnestesista = await contextAnestesista.newPage();

      // 1. Anestesista consulta su línea base en /anestesista antes de las acciones del paciente
      await loginStaff(pageAnestesista, 'ramos', 'demo1234');
      await expect(pageAnestesista).toHaveURL(/\/anestesista/);
      const cuestionariosAntes = await leerMetrica(pageAnestesista, 'Cuestionarios recibidos');

      // 2. Paciente ingresa al portal y abre la cirugía recién programada
      await loginPacienteLocal(pagePaciente);
      const enlaceCirugia = pagePaciente.locator(`a[href$="/portal/cirugias/${cirugiaId}"]`);
      await expect(enlaceCirugia).toBeVisible();
      await enlaceCirugia.click();

      // 3. Paciente responde el cuestionario preanestésico
      const navSecciones = pagePaciente.getByRole('navigation', { name: 'Secciones de la cirugía' });
      await navSecciones.getByRole('link', { name: 'Preanestésica' }).click();
      await expect(pagePaciente.locator('body')).toContainText('Pendiente');

      // Completar todas las preguntas cerradas (radios)
      const fieldsets = pagePaciente.locator('form fieldset');
      const totalRadios = await fieldsets.count();
      expect(totalRadios).toBeGreaterThan(0);
      for (let i = 0; i < totalRadios; i++) {
        await fieldsets.nth(i).locator('input[type="radio"]').first().check();
      }

      // Completar todas las preguntas abiertas (text inputs) con un marcador único verificable
      const respuestaUnica = 'Sin medicación habitual — verificado E2E cruzado';
      const inputsTexto = pagePaciente.locator('form input[type="text"]');
      const totalAbiertas = await inputsTexto.count();
      expect(totalAbiertas).toBeGreaterThan(0);
      for (let i = 0; i < totalAbiertas; i++) {
        await inputsTexto.nth(i).fill(i === 0 ? respuestaUnica : 'Sin enfermedades crónicas (E2E)');
      }

      await pagePaciente.getByRole('button', { name: 'Enviar respuestas' }).click();
      await expect(pagePaciente.locator('body')).toContainText('Listo, recibimos tus respuestas.');
      await expect(pagePaciente.locator('body')).toContainText('Respondido');

      // 4. Paciente firma el consentimiento informado (modal de lectura + modal de confirmación)
      await navSecciones.getByRole('link', { name: 'Consentimiento' }).click();
      await expect(pagePaciente.locator('body')).toContainText('Sin firmar');

      await pagePaciente.locator('button[data-abrir-consentimiento]').click();

      const dialogoConsentimiento = pagePaciente.locator('#dialogo-consentimiento');
      await expect(dialogoConsentimiento).toBeVisible();

      const btnFirmar = dialogoConsentimiento.locator('[data-firmar-consentimiento]');
      await expect(btnFirmar).toBeDisabled();

      await dialogoConsentimiento.locator('[data-casilla-consentimiento]').check();
      await expect(btnFirmar).toBeEnabled();
      await btnFirmar.click();

      // Confirmar en el diálogo nativo #dialogo-confirmar
      const dialogoConfirmar = pagePaciente.locator('#dialogo-confirmar');
      await expect(dialogoConfirmar).toBeVisible();
      await dialogoConfirmar.locator('[data-dialogo-aceptar]').click();

      await expect(pagePaciente.locator('body')).toContainText('Listo, firmaste el consentimiento informado.');
      await expect(pagePaciente.locator('body')).toContainText('Firmado');

      // 5. Anestesista recarga /anestesista y verifica el reflejo en contadores y expediente
      await pageAnestesista.reload();
      expect(await leerMetrica(pageAnestesista, 'Cuestionarios recibidos')).toBe(cuestionariosAntes + 1);

      // Abrir la pestaña de evaluación de esa cirugía y verificar las respuestas del paciente
      const linkEvaluacion = pageAnestesista.locator(`a[href$="/cirugias/${cirugiaId}?tab=evaluacion"]`);
      await expect(linkEvaluacion).toBeVisible();
      await linkEvaluacion.click();

      await expect(pageAnestesista).toHaveURL(new RegExp(`/cirugias/${cirugiaId}\\?tab=evaluacion`));
      await expect(pageAnestesista.locator('body')).toContainText('Cuestionario del paciente');
      await expect(pageAnestesista.locator('body')).toContainText(respuestaUnica);

      // Verificar en el Resumen del expediente que el consentimiento figura como Firmado
      await pageAnestesista.goto(`/cirugias/${cirugiaId}`);
      const filaConsentimiento = pageAnestesista
        .locator('li')
        .filter({ hasText: 'Consentimiento informado' })
        .first();
      await expect(filaConsentimiento).toContainText('Firmado');
    } finally {
      await contextPaciente.close();
      await contextAnestesista.close();
    }
  });
});
