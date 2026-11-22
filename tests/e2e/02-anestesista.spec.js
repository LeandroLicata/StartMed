import { test, expect } from '@playwright/test';
import {
  loginStaff,
  leerMetrica,
  seleccionarOpcionPorTexto,
  crearCirugiaDesdeUI,
} from './helpers/auth.js';

test.describe('Rol: Anestesista (ramos)', () => {
  test('1. Panel inicial y métricas: aterrizaje en /anestesista, 4 indicadores y alerta de alergia', async ({ page }) => {
    await test.step('Iniciar sesión como ramos y verificar aterrizaje en /anestesista', async () => {
      await loginStaff(page, 'ramos', 'demo1234');
      await expect(page).toHaveURL(/\/anestesista$/);
      await expect(page.getByRole('heading', { name: 'Evaluaciones anestésicas' })).toBeVisible();
      await expect(page.locator('header.sticky')).toContainText('Ramos, Hernán');
    });

    await test.step('Verificar los 4 indicadores del tablero y la alerta de evaluaciones sin cerrar', async () => {
      const pendientes = await leerMetrica(page, 'Evaluaciones pendientes');
      const completadas = await leerMetrica(page, 'Completadas');
      const riesgoAlto = await leerMetrica(page, 'ASA III o superior');
      const cuestionarios = await leerMetrica(page, 'Cuestionarios recibidos');

      expect(pendientes).toBeGreaterThan(0);
      expect(completadas).toBeGreaterThan(0);
      expect(riesgoAlto).toBeGreaterThan(0);
      expect(cuestionarios).toBeGreaterThan(0);

      await expect(page.locator('main')).toContainText('Evaluaciones sin cerrar');
    });

    await test.step('Verificar indicador visual de Alergia documentada en el caso Ramírez', async () => {
      const tarjetaRamirez = page
        .locator('main section')
        .filter({ hasText: 'Ramírez, Luis' })
        .first();

      await expect(tarjetaRamirez).toBeVisible();
      await expect(tarjetaRamirez).toContainText('ASA III');
      await expect(tarjetaRamirez).toContainText('Alergia documentada');
    });
  });

  test('2. Evaluación «Apto» y persistencia: asigna ASA III y actualiza contadores tras recargar', async ({ page }) => {
    await loginStaff(page, 'ramos', 'demo1234');
    await expect(page).toHaveURL(/\/anestesista$/);

    const pendientesAntes = await leerMetrica(page, 'Evaluaciones pendientes');
    const completadasAntes = await leerMetrica(page, 'Completadas');
    const riesgoAltoAntes = await leerMetrica(page, 'ASA III o superior');

    let nombrePaciente;

    await test.step('Abrir una cirugía pendiente (Sin ASA) desde /anestesista', async () => {
      const tarjetaPendiente = page
        .locator('main section')
        .filter({ hasText: 'Sin ASA' })
        .filter({ has: page.getByRole('link', { name: 'Cargar evaluación' }) })
        .first();

      await expect(tarjetaPendiente).toBeVisible();
      nombrePaciente = (await tarjetaPendiente.locator('a').first().innerText()).trim();

      await tarjetaPendiente.getByRole('link', { name: 'Cargar evaluación' }).click();
      await expect(page).toHaveURL(/\/cirugias\/\d+\?tab=evaluacion$/);
    });

    const obsEquipo = 'Evaluación E2E Apto: monitoreo invasivo y control glucémico intraoperatorio.';
    const obsPaciente = 'Paciente refiere buena tolerancia a anestesia previa (E2E).';

    await test.step('Completar ASA III, anestesia General y observaciones, y guardar como Apto', async () => {
      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoAsa"]'), /ASA III\b/);
      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoAnestesia"]'), /General/);
      await page.locator('textarea[name="observacionesEquipoEvaluacion"]').fill(obsEquipo);
      await page.locator('textarea[name="observacionesPacienteEvaluacion"]').fill(obsPaciente);

      await page.locator('button[name="decision"][value="apto"]').click();

      await expect(page).toHaveURL(/\/cirugias\/\d+\?tab=evaluacion$/);
      await expect(page.locator('body')).toContainText(/Evaluación (registrada|actualizada) correctamente\./);
      await expect(page.locator('body')).toContainText('Completada');
      await expect(page.locator('body')).toContainText('ASA III');
    });

    await test.step('Recargar el expediente y validar persistencia en base de datos', async () => {
      await page.reload();
      await expect(page.locator('textarea[name="observacionesEquipoEvaluacion"]')).toHaveValue(obsEquipo);
      await expect(page.locator('textarea[name="observacionesPacienteEvaluacion"]')).toHaveValue(obsPaciente);
    });

    await test.step('Volver a /anestesista, recargar y comprobar contadores y badge ASA III', async () => {
      await page.goto('/anestesista');
      await page.reload();

      expect(await leerMetrica(page, 'Evaluaciones pendientes')).toBe(pendientesAntes - 1);
      expect(await leerMetrica(page, 'Completadas')).toBe(completadasAntes + 1);
      expect(await leerMetrica(page, 'ASA III o superior')).toBe(riesgoAltoAntes + 1);

      const tarjetaActualizada = page
        .locator('main section')
        .filter({ has: page.getByRole('link', { name: nombrePaciente }) })
        .filter({ hasText: 'ASA III' })
        .first();

      await expect(tarjetaActualizada).toBeVisible();
      await expect(tarjetaActualizada).not.toContainText('Sin ASA');
    });
  });

  test('3. Evaluación «No apto» (Diferida) y edición posterior cambiando ASA', async ({ page }) => {
    await loginStaff(page, 'ramos', 'demo1234');
    await expect(page).toHaveURL(/\/anestesista$/);

    const pendientesAntes = await leerMetrica(page, 'Evaluaciones pendientes');
    const completadasAntes = await leerMetrica(page, 'Completadas');
    const riesgoAltoAntes = await leerMetrica(page, 'ASA III o superior');

    await test.step('Marcar una evaluación pendiente como «No apto» y verificar estado Diferida', async () => {
      const tarjetaPendiente = page
        .locator('main section')
        .filter({ hasText: 'Sin ASA' })
        .filter({ has: page.getByRole('link', { name: 'Cargar evaluación' }) })
        .first();

      await expect(tarjetaPendiente).toBeVisible();
      await tarjetaPendiente.getByRole('link', { name: 'Cargar evaluación' }).click();
      await expect(page).toHaveURL(/\/cirugias\/\d+\?tab=evaluacion$/);

      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoAsa"]'), /ASA II\b/);
      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoAnestesia"]'), /Regional/);
      await page
        .locator('textarea[name="observacionesEquipoEvaluacion"]')
        .fill('Diferida E2E: requiere interconsulta cardiológica previa.');

      await page.locator('button[name="decision"][value="no_apto"]').click();

      await expect(page.locator('body')).toContainText(/Evaluación (registrada|actualizada) correctamente\./);
      await expect(page.locator('body')).toContainText('Diferida');
      await expect(page.locator('body')).toContainText('ASA II');
    });

    await test.step('Confirmar en /anestesista que Diferida no suma a Completadas ni descuenta Pendientes', async () => {
      await page.goto('/anestesista');
      await page.reload();

      expect(await leerMetrica(page, 'Evaluaciones pendientes')).toBe(pendientesAntes);
      expect(await leerMetrica(page, 'Completadas')).toBe(completadasAntes);
      expect(await leerMetrica(page, 'ASA III o superior')).toBe(riesgoAltoAntes);
    });

    await test.step('Editar una evaluación ya completada (ASA II → ASA IV) y comprobar impacto en ASA III o superior', async () => {
      const tarjetaCarla = page
        .locator('main section')
        .filter({ hasText: 'Fernández, Carla' })
        .first();

      await expect(tarjetaCarla).toBeVisible();
      await expect(tarjetaCarla).toContainText('ASA II');

      await tarjetaCarla.getByRole('link', { name: 'Cargar evaluación' }).click();
      await expect(page).toHaveURL(/\/cirugias\/\d+\?tab=evaluacion$/);

      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoAsa"]'), /ASA IV\b/);
      await page
        .locator('textarea[name="observacionesEquipoEvaluacion"]')
        .fill('Reclasificada a ASA IV en edición E2E.');
      await page.locator('button[name="decision"][value="apto"]').click();

      await expect(page.locator('body')).toContainText('Evaluación actualizada correctamente.');
      await expect(page.locator('body')).toContainText('ASA IV');

      await page.goto('/anestesista');
      await page.reload();

      expect(await leerMetrica(page, 'Completadas')).toBe(completadasAntes);
      expect(await leerMetrica(page, 'ASA III o superior')).toBe(riesgoAltoAntes + 1);

      const tarjetaCarlaActualizada = page
        .locator('main section')
        .filter({ hasText: 'Fernández, Carla' })
        .first();
      await expect(tarjetaCarlaActualizada).toContainText('ASA IV');
    });
  });

  test('4. Validaciones de formulario: campos obligatorios y límite de 255 caracteres en observaciones', async ({ page }) => {
    await loginStaff(page, 'ramos', 'demo1234');
    await expect(page).toHaveURL(/\/anestesista$/);

    await test.step('Abrir una cirugía pendiente sin ASA ni tipo de anestesia cargados', async () => {
      const tarjetaPendiente = page
        .locator('main section')
        .filter({ hasText: 'Sin ASA' })
        .filter({ has: page.getByRole('link', { name: 'Cargar evaluación' }) })
        .first();
      await expect(tarjetaPendiente).toBeVisible();
      await tarjetaPendiente.getByRole('link', { name: 'Cargar evaluación' }).click();
      await expect(page).toHaveURL(/\/cirugias\/\d+\?tab=evaluacion$/);
    });

    await test.step('Comprobar bloqueo HTML5 al intentar guardar sin seleccionar ASA ni anestesia', async () => {
      await page.locator('button[name="decision"][value="apto"]').click();

      const faltaAsa = await page.locator('#idTipoAsa').evaluate((el) => el.validity.valueMissing);
      const faltaAnestesia = await page.locator('#idTipoAnestesia').evaluate((el) => el.validity.valueMissing);
      expect(faltaAsa).toBe(true);
      expect(faltaAnestesia).toBe(true);
      await expect(page.locator('body')).not.toContainText(/Evaluación (registrada|actualizada) correctamente\./);
    });

    await test.step('Comprobar validación backend al superar 255 caracteres en observaciones del equipo', async () => {
      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoAsa"]'), /ASA I\b/);
      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoAnestesia"]'), /General/);

      const textoLargo = 'X'.repeat(260);
      await page.locator('textarea[name="observacionesEquipoEvaluacion"]').fill(textoLargo);
      await page.locator('button[name="decision"][value="apto"]').click();

      const errorObs = page.locator('#observacionesEquipoEvaluacion-error');
      await expect(errorObs).toBeVisible();
      await expect(page.locator('#observacionesEquipoEvaluacion')).toHaveAttribute('aria-invalid', 'true');
      await expect(page.locator('#observacionesEquipoEvaluacion')).toHaveValue(textoLargo);
    });
  });

  test('5. Propiedad de cirugía (autorizarCirugia): solo lectura y 403 en cirugía sin anestesista asignado', async ({ browser, page }) => {
    let cirugiaSinAnestesistaId;

    await test.step('Crear como gestor una cirugía sin anestesista asignado', async () => {
      const ctxGestor = await browser.newContext();
      try {
        const pageGestor = await ctxGestor.newPage();
        await loginStaff(pageGestor, 'gonzalez', 'demo1234');
        await expect(pageGestor).toHaveURL(/\/dashboard$/);

        const fechaBase = await pageGestor.locator('input[name="hasta"]').inputValue();
        const [anio, mes, dia] = fechaBase.split('-').map(Number);
        const fechaFutura = new Date(Date.UTC(anio, mes - 1, dia + 7));
        const yyyy = fechaFutura.getUTCFullYear();
        const mm = String(fechaFutura.getUTCMonth() + 1).padStart(2, '0');
        const dd = String(fechaFutura.getUTCDate()).padStart(2, '0');

        cirugiaSinAnestesistaId = await crearCirugiaDesdeUI(pageGestor, {
          dniPaciente: '20114523', // Jorge Vidal
          tipoCirugia: /Apendicectomía/,
          quirofano: /Quirófano de urgencias/,
          fechaHoraInicio: `${yyyy}-${mm}-${dd}T14:00`,
          cirujano: /Pérez/,
          anestesista: null,
          cobertura: 'existente',
        });
      } finally {
        await ctxGestor.close();
      }
    });

    await test.step('Verificar que ramos ve tab=evaluacion en solo lectura sin formulario editable', async () => {
      await loginStaff(page, 'ramos', 'demo1234');
      await page.goto(`/cirugias/${cirugiaSinAnestesistaId}?tab=evaluacion`);

      await expect(page.locator('body')).toContainText(
        'Sin evaluación cargada. Solo el anestesista asignado puede cargarla.',
      );
      await expect(page.locator('select[name="idTipoAsa"]')).toHaveCount(0);
      await expect(page.locator('button[name="decision"]')).toHaveCount(0);
    });

    await test.step('Verificar que el acceso directo a /anestesista/{id}/evaluar responde 403', async () => {
      const resp = await page.goto(`/anestesista/${cirugiaSinAnestesistaId}/evaluar`);
      expect(resp.status()).toBe(403);
    });
  });

  test('6. Módulo /admin/cuestionario: congelamiento, versionado, CRUD de preguntas, retiro y auditoría', async ({ browser, page }) => {
    await loginStaff(page, 'ramos', 'demo1234');

    await test.step('Verificar que la versión vigente sembrada con respuestas está congelada (Solo lectura)', async () => {
      await page.goto('/admin/cuestionario');
      await expect(page.getByRole('heading', { name: 'Cuestionario preanestésico' })).toBeVisible();

      const filaEnUso = page.locator('table tbody tr').filter({ hasText: 'En uso' }).first();
      await expect(filaEnUso).toBeVisible();
      await filaEnUso.getByRole('link', { name: 'Ver' }).click();

      await expect(page).toHaveURL(/\/admin\/cuestionario\/\d+$/);
      await expect(page.locator('body')).toContainText('Solo lectura');
      await expect(page.locator('body')).toContainText('se respondieron con esta versión');
      await expect(page.locator('section', { hasText: 'Agregar una pregunta' })).toHaveCount(0);
    });

    await test.step('Publicar nueva versión clonada y verificar que queda editable', async () => {
      await page.getByRole('link', { name: 'Volver a versiones' }).click();
      await page.getByRole('button', { name: 'Publicar versión nueva' }).click();

      await expect(page).toHaveURL(/\/admin\/cuestionario\/\d+$/);
      await expect(page.locator('body')).toContainText('Versión nueva publicada');
      await expect(page.locator('section').filter({ hasText: 'Agregar una pregunta' })).toBeVisible();
    });

    const preguntaCerrada = '¿Tuviste reacciones al látex? (E2E)';
    const preguntaCerradaEditada = '¿Presentás alergia al látex? (E2E)';
    const preguntaAbierta = '¿Usás algún dispositivo médico implantado? (E2E)';

    await test.step('Agregar pregunta cerrada con opciones, editarla y quitar una opción', async () => {
      const seccionAgregar = page
        .locator('main section')
        .filter({ has: page.getByRole('heading', { name: 'Agregar una pregunta' }) });

      await seccionAgregar
        .locator('input[name="nombrePreguntaConfigTipoExamenPreAnestesicoPregunta"]')
        .fill(preguntaCerrada);
      await seccionAgregar
        .locator('input[type="checkbox"][name="requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta"]')
        .check();
      await seccionAgregar.getByRole('button', { name: 'Agregar' }).click();

      await expect(page.locator('body')).toContainText('Pregunta agregada.');

      // Localizar la pregunta cerrada agregada y cargar dos opciones
      const itemCerrada = page
        .locator('main ol > li')
        .filter({ has: page.locator(`input[value="${preguntaCerrada}"]`) })
        .first();
      await expect(itemCerrada).toBeVisible();

      await itemCerrada
        .locator('input[name="nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta"]')
        .fill('No');
      await itemCerrada.getByRole('button', { name: 'Agregar' }).click();
      await expect(page.locator('body')).toContainText('Opción agregada.');

      const itemConUnaOpcion = page
        .locator('main ol > li')
        .filter({ has: page.locator(`input[value="${preguntaCerrada}"]`) })
        .first();
      await itemConUnaOpcion
        .locator('input[name="nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta"]')
        .fill('Sí, leve');
      await itemConUnaOpcion.getByRole('button', { name: 'Agregar' }).click();
      await expect(page.locator('body')).toContainText('Opción agregada.');

      // Editar el texto de la pregunta cerrada
      const itemParaEditar = page
        .locator('main ol > li')
        .filter({ has: page.locator(`input[value="${preguntaCerrada}"]`) })
        .first();
      await itemParaEditar
        .locator('input[name="nombrePreguntaConfigTipoExamenPreAnestesicoPregunta"]')
        .fill(preguntaCerradaEditada);
      await itemParaEditar.getByRole('button', { name: 'Guardar' }).click();
      await expect(page.locator('body')).toContainText('Pregunta actualizada.');

      // Quitar la opción "Sí, leve"
      const itemEditado = page
        .locator('main ol > li')
        .filter({ has: page.locator(`input[value="${preguntaCerradaEditada}"]`) })
        .first();
      await itemEditado
        .locator('span', { hasText: 'Sí, leve' })
        .getByRole('button', { name: 'Quitar opción' })
        .click();
      await expect(page.locator('body')).toContainText('Opción eliminada.');
    });

    await test.step('Agregar pregunta abierta y eliminar ambas preguntas de prueba con #dialogo-confirmar', async () => {
      const seccionAgregar = page
        .locator('main section')
        .filter({ has: page.getByRole('heading', { name: 'Agregar una pregunta' }) });

      await seccionAgregar
        .locator('input[name="nombrePreguntaConfigTipoExamenPreAnestesicoPregunta"]')
        .fill(preguntaAbierta);
      await seccionAgregar.getByRole('button', { name: 'Agregar' }).click();
      await expect(page.locator('body')).toContainText('Pregunta agregada.');

      const itemAbierta = page
        .locator('main ol > li')
        .filter({ has: page.locator(`input[value="${preguntaAbierta}"]`) })
        .first();
      await expect(itemAbierta).toContainText('Se responde con texto libre.');

      // Eliminar la pregunta abierta confirmando en #dialogo-confirmar
      await itemAbierta.getByRole('button', { name: 'Eliminar pregunta' }).click();
      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page.locator('body')).toContainText('Pregunta eliminada.');
      await expect(page.locator(`input[value="${preguntaAbierta}"]`)).toHaveCount(0);
    });

    await test.step('Retirar la versión nueva usando #dialogo-confirmar y dejar una versión vigente lista', async () => {
      await page.getByRole('button', { name: 'Retirar' }).click();
      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await expect(dialogo.locator('#dialogo-confirmar-titulo')).toContainText('Retirar el cuestionario preanestésico');
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page).toHaveURL(/\/admin\/cuestionario$/);
      await expect(page.locator('body')).toContainText('Cuestionario retirado.');

      // Publicar una versión vigente limpia (1 cerrada + 1 abierta) para no dejar el portal sin cuestionario
      await page.getByRole('button', { name: 'Publicar versión nueva' }).click();
      await expect(page).toHaveURL(/\/admin\/cuestionario\/\d+$/);

      const seccionAgregar = page
        .locator('main section')
        .filter({ has: page.getByRole('heading', { name: 'Agregar una pregunta' }) });

      await seccionAgregar
        .locator('input[name="nombrePreguntaConfigTipoExamenPreAnestesicoPregunta"]')
        .fill('¿Tenés alergia a algún medicamento?');
      await seccionAgregar
        .locator('input[type="checkbox"][name="requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta"]')
        .check();
      await seccionAgregar.getByRole('button', { name: 'Agregar' }).click();

      const itemAlergia = page
        .locator('main ol > li')
        .filter({ has: page.locator('input[value="¿Tenés alergia a algún medicamento?"]') })
        .first();
      await itemAlergia
        .locator('input[name="nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta"]')
        .fill('No');
      await itemAlergia.getByRole('button', { name: 'Agregar' }).click();

      await seccionAgregar
        .locator('input[name="nombrePreguntaConfigTipoExamenPreAnestesicoPregunta"]')
        .fill('¿Tomás medicación habitual?');
      await seccionAgregar.getByRole('button', { name: 'Agregar' }).click();
      await expect(page.locator('body')).toContainText('Pregunta agregada.');
    });

    await test.step('Verificar en /admin/auditoria (como admin) los movimientos registrados por ramos', async () => {
      const ctxAdmin = await browser.newContext();
      try {
        const pageAdmin = await ctxAdmin.newPage();
        await loginStaff(pageAdmin, 'admin', 'admin1234');
        await pageAdmin.goto('/admin/auditoria');

        const filaCuestionarioBaja = pageAdmin
          .locator('table tbody tr')
          .filter({ hasText: 'ramos' })
          .filter({ hasText: 'Baja' })
          .filter({ hasText: 'Cuestionario preanestésico' })
          .first();
        await expect(filaCuestionarioBaja).toBeVisible();

        const filaPreguntaEdicion = pageAdmin
          .locator('table tbody tr')
          .filter({ hasText: 'ramos' })
          .filter({ hasText: 'Edición' })
          .filter({ hasText: preguntaCerradaEditada })
          .first();
        await expect(filaPreguntaEdicion).toBeVisible();
      } finally {
        await ctxAdmin.close();
      }
    });
  });

  test('7. Expediente clínico, padrón /pacientes en solo lectura y vista previa del portal', async ({ page }) => {
    await loginStaff(page, 'ramos', 'demo1234');
    await expect(page).toHaveURL(/\/anestesista$/);

    await test.step('Abrir el expediente de Jorge Vidal y verificar ausencia de botones de Gestor/Cirujano', async () => {
      const tarjetaVidal = page
        .locator('main section')
        .filter({ hasText: 'Vidal, Jorge' })
        .first();
      await expect(tarjetaVidal).toBeVisible();
      await tarjetaVidal.getByRole('link', { name: 'Vidal, Jorge' }).click();
      await expect(page).toHaveURL(/\/cirugias\/\d+$/);

      // Reprogramar es exclusivo de Gestor
      await expect(page.getByRole('button', { name: 'Reprogramar' })).toHaveCount(0);

      // En el menú de tres puntos debe ver requerimientos pero NO Cancelar Cirugía
      await page.locator('#btn-menu-cirugia').click();
      const menu = page.locator('#dropdown-menu-cirugia');
      await expect(menu).toBeVisible();
      await expect(menu.getByRole('button', { name: 'Activar Implante' })).toBeVisible();
      await expect(menu.getByRole('button', { name: 'Requerir Hemoderivados' })).toBeVisible();
      await expect(menu.getByRole('button', { name: 'Requerir Hisopado SAMR' })).toBeVisible();
      await expect(menu.getByRole('button', { name: 'Cancelar Cirugía' })).toHaveCount(0);
    });

    await test.step('Agregar un estudio prequirúrgico permitido y validar persistencia tras recargar', async () => {
      await page.getByRole('link', { name: 'Estudios prequirúrgicos' }).click();
      await expect(page).toHaveURL(/tab=estudios/);

      await page.getByRole('button', { name: 'Agregar estudio' }).click();
      const modal = page.locator('#modal-agregar-estudio');
      await expect(modal).toBeVisible();

      await seleccionarOpcionPorTexto(modal.locator('select[name="idTipoEstudio"]'), /Radiografía de tórax/);
      await modal.getByRole('button', { name: 'Agregar' }).click();

      await expect(page.locator('body')).toContainText('Estudio agregado correctamente.');
      await expect(page.locator('body')).toContainText('Radiografía de tórax');

      await page.reload();
      await expect(page.locator('body')).toContainText('Radiografía de tórax');
    });

    await test.step('Abrir «Ver como el paciente» (/cirugias/{id}/portal-paciente)', async () => {
      await page.getByRole('link', { name: 'Ver como el paciente' }).click();
      await expect(page).toHaveURL(/\/cirugias\/\d+\/portal-paciente$/);
      await expect(page.locator('body')).toContainText('Hernioplastia inguinal');
    });

    await test.step('Consultar padrón /pacientes y ficha individual en solo lectura', async () => {
      await page.goto('/pacientes');
      await expect(page.getByRole('heading', { name: 'Pacientes', exact: true })).toBeVisible();
      await expect(page.getByRole('link', { name: 'Nuevo paciente' })).toHaveCount(0);

      await page.locator('input[name="buscar"]').fill('14320876');
      await page.getByRole('button', { name: 'Buscar' }).click();

      const tarjetaPaciente = page
        .locator('main section section')
        .filter({ hasText: 'Ramírez, Luis' })
        .first();
      await expect(tarjetaPaciente).toBeVisible();
      await expect(tarjetaPaciente.getByRole('link', { name: 'Editar' })).toHaveCount(0);

      await tarjetaPaciente.getByRole('link', { name: 'Ver historial' }).click();
      await expect(page).toHaveURL(/\/pacientes\/\d+$/);
      await expect(page.locator('body')).toContainText('Ramírez, Luis');
      await expect(page.getByRole('link', { name: 'Editar datos' })).toHaveCount(0);
    });
  });

  test('8. Permisos 403 en rutas ajenas y cierre de sesión (Logout)', async ({ page }) => {
    await loginStaff(page, 'ramos', 'demo1234');
    await expect(page).toHaveURL(/\/anestesista$/);

    await test.step('Comprobar HTTP 403 en rutas exclusivas de otros roles', async () => {
      const rutasProhibidas = [
        '/dashboard',
        '/cirugias/nueva',
        '/cirujano',
        '/direccion',
        '/admin/usuarios',
        '/pacientes/nuevo',
      ];

      for (const ruta of rutasProhibidas) {
        const resp = await page.goto(ruta);
        expect(resp.status(), `Se esperaba 403 en ${ruta}`).toBe(403);
      }
    });

    await test.step('Cerrar sesión con el botón Salir y verificar protección de /anestesista', async () => {
      await page.goto('/anestesista');
      await page.locator('header.sticky').getByRole('button', { name: 'Salir' }).click();
      await expect(page).toHaveURL(/\/login$/);

      await page.goto('/anestesista');
      await expect(page).toHaveURL(/\/login$/);
    });
  });

  // Bug conocido documentado en CLAUDE.md: Navegacion::SECCIONES incluye 'Anestesista'
  // en Agenda (cirujano.agenda) e Historial de cirugías (cirujano.historial), pero esas
  // rutas tienen middleware rol:Cirujano y devuelven 403 al hacer clic desde el sidebar.
  test.fixme('9. [Bug conocido] Enlaces Agenda e Historial de cirugías del sidebar deberían devolver 200', async ({ page }) => {
    await loginStaff(page, 'ramos', 'demo1234');
    await expect(page).toHaveURL(/\/anestesista$/);

    const nav = page.getByRole('navigation', { name: 'Secciones' });

    await test.step('Clic en Agenda (/cirujano/agenda) desde el sidebar de ramos', async () => {
      const [respAgenda] = await Promise.all([
        page.waitForResponse((r) => r.url().includes('/cirujano/agenda') && r.request().isNavigationRequest()),
        nav.getByRole('link', { name: 'Agenda' }).click(),
      ]);
      expect(respAgenda.status()).toBe(200);
    });

    await test.step('Clic en Historial de cirugías (/cirujano/historial) desde el sidebar de ramos', async () => {
      await page.goto('/anestesista');
      const [respHistorial] = await Promise.all([
        page.waitForResponse((r) => r.url().includes('/cirujano/historial') && r.request().isNavigationRequest()),
        nav.getByRole('link', { name: 'Historial de cirugías' }).click(),
      ]);
      expect(respHistorial.status()).toBe(200);
    });
  });
});
