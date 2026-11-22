import { test, expect } from '@playwright/test';
import {
  loginStaff,
  leerMetrica,
  seleccionarOpcionPorTexto,
  fijarFechaHora,
  crearCirugiaDesdeUI,
} from './helpers/auth.js';

function sumarDiasISO(fechaISO, dias) {
  const [anio, mes, dia] = fechaISO.split('-').map(Number);
  const fecha = new Date(Date.UTC(anio, mes - 1, dia + dias));
  const yyyy = fecha.getUTCFullYear();
  const mm = String(fecha.getUTCMonth() + 1).padStart(2, '0');
  const dd = String(fecha.getUTCDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

test.describe('Rol: Gestor de quirófano (gonzalez)', () => {
  test('1. Tablero /dashboard, filtros y listado general /cirugias', async ({ page }) => {
    await test.step('Iniciar sesión como gonzalez y verificar aterrizaje en /dashboard', async () => {
      await loginStaff(page, 'gonzalez', 'demo1234');
      await expect(page).toHaveURL(/\/dashboard$/);
      await expect(page.getByRole('heading', { name: 'Tablero de quirófano' })).toBeVisible();
      await expect(page.locator('header.sticky')).toContainText('González, Romina');
    });

    await test.step('Verificar indicadores del día y bloque Agenda de hoy', async () => {
      expect(await leerMetrica(page, 'Cirugías hoy')).toBeGreaterThan(0);
      expect(await leerMetrica(page, 'Quirófanos habilitados')).toBeGreaterThanOrEqual(4);
      expect(await leerMetrica(page, 'Casos en riesgo')).toBeGreaterThanOrEqual(0);

      await expect(page.getByRole('heading', { name: 'Agenda de hoy' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Cirugías de la semana' })).toBeVisible();
    });

    await test.step('Filtrar Cirugías de la semana en /dashboard y limpiar filtros', async () => {
      await page.locator('input[name="q"]').fill('García');
      await page.getByRole('button', { name: 'Filtrar' }).click();

      await expect(page).toHaveURL(/q=Garc/);
      await expect(page.locator('table tbody')).toContainText('García, María');

      await page.getByRole('link', { name: 'Limpiar filtros' }).click();
      await expect(page).toHaveURL(/\/dashboard$/);
    });

    await test.step('Navegar al listado general /cirugias, verificar botón Nueva cirugía y filtrar', async () => {
      await page.getByRole('navigation', { name: 'Secciones' }).getByRole('link', { name: 'Cirugías' }).click();
      await expect(page).toHaveURL(/\/cirugias$/);
      await expect(page.getByRole('link', { name: 'Nueva cirugía' })).toBeVisible();

      await page.locator('input[name="q"]').fill('Ramírez');
      await page.getByRole('button', { name: 'Filtrar' }).click();
      await expect(page.locator('table tbody')).toContainText('Ramírez, Luis');
    });
  });

  test('2. Alta de cirugía con paciente existente: conflicto de horario y alta exitosa en /dashboard', async ({ page }) => {
    await loginStaff(page, 'gonzalez', 'demo1234');
    await expect(page).toHaveURL(/\/dashboard$/);

    // Obtener la fecha "hasta" de la semana corriente (siempre dentro del tablero por defecto)
    const fechaSemanaHasta = await page.locator('input[name="hasta"]').inputValue();
    expect(fechaSemanaHasta).toMatch(/^\d{4}-\d{2}-\d{2}$/);

    // Obtener la fecha exacta de hoy según el servidor desde la celda resaltada de /agenda
    await page.goto('/agenda');
    const hrefHoy = await page.locator('a.border-hu-dorado').first().getAttribute('href');
    const fechaHoyMatch = hrefHoy?.match(/\/agenda\/(\d{4}-\d{2}-\d{2})$/);
    expect(fechaHoyMatch, `No se pudo detectar la fecha de hoy en /agenda: ${hrefHoy}`).not.toBeNull();
    const fechaHoy = fechaHoyMatch[1];

    await test.step('Comprobar conflicto de horario en Quirófano 1 hoy a las 08:00 (ocupado por María García 07:30–09:30)', async () => {
      await page.goto('/cirugias/nueva');
      await page.locator('#input-buscar-paciente').fill('20114523');

      const sugerencia = page
        .locator('#dropdown-pacientes-contenido > div')
        .filter({ hasText: '20114523' })
        .first();
      await expect(sugerencia).toBeVisible();
      await sugerencia.click();
      await expect(page).toHaveURL(/\/cirugias\/nueva\?persona=\d+/);

      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoCirugia"]'), /Artrodesis lumbar/);
      await seleccionarOpcionPorTexto(page.locator('select[name="idQuirofano"]'), /Quirófano central/);
      await fijarFechaHora(page, 'fechaHoraCirugia', `${fechaHoy}T08:00`);
      await seleccionarOpcionPorTexto(page.locator('select[name="idPersonalCirujano"]'), /Pérez/);

      const btnCrear = page.getByRole('button', { name: 'Crear cirugía' });
      await expect(btnCrear).toBeDisabled();

      await page.getByRole('button', { name: 'Comprobar disponibilidad' }).click();

      await expect(page.locator('body')).toContainText('Resultado de la comprobación');
      await expect(page.locator('body')).toContainText('Quirófano: Ocupado en ese horario');
      await expect(page.locator('body')).toContainText('Cirujano: Ocupado en ese horario');
      await expect(btnCrear).toBeDisabled();
    });

    let cirugiaId;

    await test.step('Cambiar a Quirófano de urgencias (libre) a las 18:00, comprobar disponibilidad y crear cirugía', async () => {
      cirugiaId = await crearCirugiaDesdeUI(page, {
        dniPaciente: '20114523', // Jorge Vidal
        tipoCirugia: /Artrodesis lumbar/,
        quirofano: /Quirófano de urgencias/,
        fechaHoraInicio: `${fechaSemanaHasta}T18:00`,
        cirujano: /Pérez/,
        anestesista: /Ramos/,
        cobertura: 'existente',
      });

      await expect(page.locator('body')).toContainText('Vidal');
      await expect(page.locator('body')).toContainText('Artrodesis lumbar');
      await expect(page.locator('body')).toContainText('En espera');
    });

    await test.step('Confirmar en /dashboard (tras recargar) que aparece listada con estado inicial En espera', async () => {
      await page.goto('/dashboard');
      await page.reload();

      const filaNueva = page
        .locator('table tbody tr')
        .filter({ has: page.locator(`a[href$="/cirugias/${cirugiaId}"]`) });

      await expect(filaNueva).toBeVisible();
      await expect(filaNueva).toContainText('Vidal');
      await expect(filaNueva).toContainText('Artrodesis lumbar');
      await expect(filaNueva).toContainText('En espera');
    });
  });

  test('3. Alta rápida de paciente nuevo + cirugía sin equipo completo (En espera de confirmación)', async ({ page }) => {
    await loginStaff(page, 'gonzalez', 'demo1234');
    await expect(page).toHaveURL(/\/dashboard$/);

    const fechaSemanaHasta = await page.locator('input[name="hasta"]').inputValue();
    const fechaLibre = sumarDiasISO(fechaSemanaHasta, 8);
    const dniNuevo = '41998877';

    await test.step('Buscar DNI inexistente en /cirugias/nueva y dar de alta al paciente inline', async () => {
      await page.goto('/cirugias/nueva');
      await page.locator('#input-buscar-paciente').fill(dniNuevo);
      await page.getByRole('button', { name: 'Buscar' }).click();

      await expect(page.locator('body')).toContainText('Podés darlo de alta ahora para continuar con la cirugía.');
      await expect(page.getByRole('heading', { name: 'Dar de alta al paciente' })).toBeVisible();

      await page.locator('input[name="apellidos"]').fill('Sosa');
      await page.locator('input[name="nombres"]').fill('Valentina');
      await page.locator('input[name="documento"]').fill(dniNuevo);
      await page.locator('input[name="contacto_email_direccion"]').fill('valentina.sosa@example.com');
      await page.locator('input[name="contacto_telefono_numero"]').fill('2614998877');

      await page.getByRole('button', { name: 'Dar de alta y continuar' }).click();
      await expect(page).toHaveURL(/\/cirugias\/nueva\?persona=\d+/);
      await expect(page.locator('body')).toContainText('Sosa, Valentina');
    });

    await test.step('Crear cirugía Particular sin cirujano ni anestesista y con implante requerido', async () => {
      await page.locator('input[name="cobertura"][value="particular"]').check();
      await seleccionarOpcionPorTexto(page.locator('select[name="idTipoCirugia"]'), /Hernioplastia inguinal/);
      await seleccionarOpcionPorTexto(page.locator('select[name="idQuirofano"]'), /Quirófano de urgencias/);
      await fijarFechaHora(page, 'fechaHoraCirugia', `${fechaLibre}T11:00`);
      await page.locator('input[name="requiereImplante"]').check();

      await page.getByRole('button', { name: 'Comprobar disponibilidad' }).click();
      await expect(page.locator('body')).toContainText('Quirófano: Disponible');

      const btnCrear = page.getByRole('button', { name: 'Crear cirugía' });
      await expect(btnCrear).toBeEnabled();
      await btnCrear.click();

      await expect(page).toHaveURL(/\/cirugias\/\d+$/);
      await expect(page.locator('body')).toContainText('Cirugía creada correctamente.');
      await expect(page.locator('body')).toContainText('En espera de confirmación');
      await expect(page.locator('body')).toContainText('Con implante');
    });
  });

  test('4. Agenda /agenda: vista Mes, cambio de período, vista Semana y detalle diario', async ({ page }) => {
    await loginStaff(page, 'gonzalez', 'demo1234');

    await test.step('Abrir Agenda en vista Mes (por defecto) y navegar al mes siguiente y volver a Hoy', async () => {
      await page.getByRole('navigation', { name: 'Secciones' }).getByRole('link', { name: 'Agenda' }).click();
      await expect(page).toHaveURL(/\/agenda$/);
      await expect(page.getByRole('heading', { name: 'Agenda', exact: true })).toBeVisible();

      const mesInicial = (await page.locator('main h2').first().innerText()).trim();
      await page.getByRole('link', { name: 'Mes siguiente' }).click();
      await expect(page).toHaveURL(/mes=\d{4}-\d{2}/);
      await expect(page.locator('main h2').first()).not.toHaveText(mesInicial);

      await page.getByRole('link', { name: 'Hoy' }).click();
      await expect(page).toHaveURL(/\/agenda$/);
    });

    await test.step('Cambiar a vista Semana y navegar a la semana siguiente', async () => {
      await page.getByRole('group', { name: 'Vista de la agenda' }).getByRole('link', { name: 'Semana' }).click();
      await expect(page).toHaveURL(/vista=semana/);

      await page.getByRole('link', { name: 'Semana siguiente' }).click();
      await expect(page).toHaveURL(/semana=\d{4}-\d{2}-\d{2}/);

      await page.getByRole('group', { name: 'Vista de la agenda' }).getByRole('link', { name: 'Mes' }).click();
      await expect(page).toHaveURL(/\/agenda$/);
    });

    await test.step('Entrar al detalle del día actual (/agenda/YYYY-MM-DD) y verificar cirugías del día', async () => {
      const celdaHoy = page.locator('a.border-hu-dorado').first();
      await expect(celdaHoy).toBeVisible();
      await celdaHoy.click();

      await expect(page).toHaveURL(/\/agenda\/\d{4}-\d{2}-\d{2}$/);
      await expect(page.getByRole('heading', { name: 'Cirugías del día' })).toBeVisible();
      await expect(page.getByRole('link', { name: 'Nueva cirugía este día' })).toBeVisible();
      await expect(page.locator('table tbody tr').first()).toBeVisible();
    });
  });

  test('5. Expediente /cirugias/{id}: Reprogramación (clonado) y Cancelación con #dialogo-confirmar', async ({ page }) => {
    await loginStaff(page, 'gonzalez', 'demo1234');
    await expect(page).toHaveURL(/\/dashboard$/);

    const fechaSemanaHasta = await page.locator('input[name="hasta"]').inputValue();
    const fechaOriginal = sumarDiasISO(fechaSemanaHasta, 9);
    const fechaNueva = sumarDiasISO(fechaSemanaHasta, 10);

    let cirugiaOriginalId;
    let cirugiaNuevaId;

    await test.step('Crear una cirugía dedicada para probar reprogramación y cancelación', async () => {
      cirugiaOriginalId = await crearCirugiaDesdeUI(page, {
        dniPaciente: '20114523', // Jorge Vidal
        tipoCirugia: /Colecistectomía laparoscópica/,
        quirofano: /Quirófano de urgencias/,
        fechaHoraInicio: `${fechaOriginal}T10:00`,
        cirujano: /Pérez/,
        anestesista: null,
        cobertura: 'existente',
      });
    });

    await test.step('Reprogramar la cirugía desde el modal y verificar nueva cirugía + original en Reprogramada', async () => {
      await page.getByRole('button', { name: 'Reprogramar' }).click();

      const modal = page.locator('#modal-reprogramar');
      await expect(modal).toBeVisible();

      await modal.locator('input[name="fecha"]').fill(fechaNueva);
      await seleccionarOpcionPorTexto(modal.locator('select[name="idQuirofano"]'), /Quirófano de urgencias/);
      await modal.locator('input[name="hora_inicio"]').fill('11:00');
      await modal.locator('input[name="hora_fin"]').fill('13:00');
      await modal.locator('select[name="cobertura"]').selectOption('misma');

      await modal.getByRole('button', { name: 'Confirmar Reprogramación' }).click();

      await expect(page).toHaveURL(/\/cirugias\/\d+$/);
      await expect(page.locator('body')).toContainText('Cirugía reprogramada exitosamente.');

      const matchNueva = page.url().match(/\/cirugias\/(\d+)$/);
      expect(matchNueva).not.toBeNull();
      cirugiaNuevaId = matchNueva[1];
      expect(cirugiaNuevaId).not.toBe(cirugiaOriginalId);

      // Comprobar que la cirugía original pasó a estado Reprogramada
      await page.goto(`/cirugias/${cirugiaOriginalId}`);
      await expect(page.locator('body')).toContainText('Reprogramada');
    });

    await test.step('Cancelar la cirugía reprogramada confirmando en #dialogo-confirmar y verificar persistencia', async () => {
      await page.goto(`/cirugias/${cirugiaNuevaId}`);

      await page.locator('#btn-menu-cirugia').click();
      const menu = page.locator('#dropdown-menu-cirugia');
      await expect(menu).toBeVisible();
      await menu.getByRole('button', { name: 'Cancelar Cirugía' }).click();

      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await expect(dialogo.locator('#dialogo-confirmar-titulo')).toContainText('Cancelar Cirugía');
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page).toHaveURL(new RegExp(`/cirugias/${cirugiaNuevaId}$`));
      await expect(page.locator('body')).toContainText('La cirugía ha sido cancelada y los horarios liberados.');
      await expect(page.locator('body')).toContainText('Suspendida');

      // Recargar y comprobar persistencia + desaparición de Cancelar Cirugía
      await page.reload();
      await expect(page.locator('body')).toContainText('Suspendida');
      await page.locator('#btn-menu-cirugia').click();
      await expect(page.locator('#dropdown-menu-cirugia').getByRole('button', { name: 'Cancelar Cirugía' })).toHaveCount(0);
    });
  });

  test('6. Expediente: actualizar autorización, vista previa del paciente y evaluación en solo lectura', async ({ page }) => {
    test.slow();
    await loginStaff(page, 'gonzalez', 'demo1234');
    await expect(page).toHaveURL(/\/dashboard$/);

    await test.step('Abrir el expediente de Jorge Vidal (Hernioplastia inguinal) y actualizar su autorización a Aprobada', async () => {
      await page.goto('/cirugias');
      await page.locator('input[name="q"]').fill('Vidal');
      await page.getByRole('button', { name: 'Filtrar' }).click();

      const filaVidal = page
        .locator('table tbody tr')
        .filter({ hasText: 'Vidal, Jorge' })
        .filter({ hasText: 'Hernioplastia inguinal' })
        .first();
      await expect(filaVidal).toBeVisible();
      await filaVidal.locator('a').first().click();

      await expect(page).toHaveURL(/\/cirugias\/\d+$/);

      await page.getByRole('link', { name: 'Autorización financiador' }).click();
      await expect(page).toHaveURL(/tab=autorizacion/);

      await page.getByRole('button', { name: 'Actualizar estado' }).click();
      const modalAut = page.locator('#modal-estado-autorizacion');
      await expect(modalAut).toBeVisible();

      await seleccionarOpcionPorTexto(modalAut.locator('select[name="idEstadoAutCirugia"]'), /^Aprobada$/);
      await modalAut.locator('input[name="observacionesAutoCirugiaEstado"]').fill('Aprobación registrada en E2E gestor.');
      await modalAut.getByRole('button', { name: 'Actualizar' }).click();

      await expect(page.locator('body')).toContainText('Estado de la autorización actualizado.');
      await page.reload();
      await expect(page.locator('body')).toContainText('Aprobada');
      await expect(page.locator('body')).toContainText('Aprobación registrada en E2E gestor.');
    });

    await test.step('Comprobar que la solapa Evaluación anestésica es solo lectura para el gestor', async () => {
      await page.getByRole('link', { name: 'Evaluación anestésica' }).click();
      await expect(page).toHaveURL(/tab=evaluacion/);

      await expect(page.locator('select[name="idTipoAsa"]')).toHaveCount(0);
      await expect(page.locator('button[name="decision"]')).toHaveCount(0);
    });

    await test.step('Abrir «Ver como el paciente» (/cirugias/{id}/portal-paciente)', async () => {
      await page.getByRole('link', { name: 'Ver como el paciente' }).click();
      await expect(page).toHaveURL(/\/cirugias\/\d+\/portal-paciente$/);
      await expect(page.locator('body')).toContainText('Hernioplastia inguinal');
    });
  });

  test('7. Pacientes y Coberturas (/pacientes): validaciones, alta, edición, obras sociales y auditoría', async ({ browser, page }) => {
    test.slow();
    await loginStaff(page, 'gonzalez', 'demo1234');

    const dniNuevo = '42887766';

    await test.step('Validar rechazo de DNI duplicado en /pacientes/nuevo', async () => {
      await page.goto('/pacientes/nuevo');
      await expect(page.getByRole('heading', { name: 'Nuevo paciente', level: 1 })).toBeVisible();

      await page.locator('input[name="documento"]').fill('28456789'); // DNI de María García
      await page.locator('input[name="apellidos"]').fill('Duplicado');
      await page.locator('input[name="nombres"]').fill('Test');
      await page.locator('input[name="contacto_email_direccion"]').fill('duplicado@example.com');
      await page.locator('input[name="telefono_numero"]').fill('2614000000');
      await page.locator('input[name="direccionPersona"]').fill('Calle Falsa 123');
      await page.getByRole('button', { name: 'Registrar paciente' }).click();

      await expect(page.locator('#documento-error')).toBeVisible();
      await expect(page.locator('#documento')).toHaveAttribute('aria-invalid', 'true');
    });

    let pacienteUrl;

    await test.step('Registrar paciente nuevo válido y verificar ficha', async () => {
      await page.locator('input[name="documento"]').fill(dniNuevo);
      await page.locator('input[name="apellidos"]').fill('Mendoza');
      await page.locator('input[name="nombres"]').fill('Lucas');
      await page.locator('input[name="contacto_email_direccion"]').fill('lucas.mendoza@example.com');
      await page.locator('input[name="telefono_numero"]').fill('2614887766');
      await page.locator('input[name="direccionPersona"]').fill('San Martín 1234, Mendoza');
      await page.locator('input[type="checkbox"][name="es_cronico"]').check();

      await page.getByRole('button', { name: 'Registrar paciente' }).click();

      await expect(page).toHaveURL(/\/pacientes\/\d+$/);
      await expect(page.locator('body')).toContainText('Paciente Mendoza, Lucas registrado correctamente.');
      await expect(page.locator('body')).toContainText('San Martín 1234, Mendoza');
      await expect(page.locator('body')).toContainText('Crónico');
      pacienteUrl = page.url();
    });

    await test.step('Editar datos del paciente y confirmar persistencia tras recargar', async () => {
      await page.getByRole('link', { name: 'Editar datos' }).click();
      await expect(page).toHaveURL(/\/pacientes\/\d+\/editar$/);

      await page.locator('input[name="direccionPersona"]').fill('Belgrano 567, Mendoza');
      await page.locator('input[type="checkbox"][name="no_acepta_donacion_sanguinea"]').check();
      await page.getByRole('button', { name: 'Guardar cambios' }).click();

      await expect(page).toHaveURL(pacienteUrl);
      await expect(page.locator('body')).toContainText('Datos del paciente actualizados correctamente.');

      await page.reload();
      await expect(page.locator('body')).toContainText('Belgrano 567, Mendoza');
      await expect(page.locator('body')).toContainText('No donación');
    });

    await test.step('Gestionar coberturas: alta, rechazo de duplicada, cambio de predeterminada y baja con #dialogo-confirmar', async () => {
      const formCobertura = page.locator('form[action*="/obras-sociales"]').filter({ has: page.locator('select[name="idPlan"]') });

      // 1. Primera obra social (OSDE 410) queda automáticamente como Predeterminada
      await seleccionarOpcionPorTexto(formCobertura.locator('select[name="idPlan"]'), /OSDE.*410/);
      await formCobertura.locator('input[name="nroBeneficiario"]').fill('990011');
      await formCobertura.getByRole('button', { name: 'Agregar' }).click();

      await expect(page.locator('body')).toContainText('Obra social agregada y marcada como predeterminada.');
      await expect(page.locator('body')).toContainText('Predeterminada');

      // 2. Intentar agregar el mismo plan vigente (OSDE 410) → error de duplicado
      await seleccionarOpcionPorTexto(formCobertura.locator('select[name="idPlan"]'), /OSDE.*410/);
      await formCobertura.locator('input[name="nroBeneficiario"]').fill('990011');
      await formCobertura.getByRole('button', { name: 'Agregar' }).click();

      await expect(page.locator('body')).toContainText('La persona ya tiene ese plan entre sus obras sociales vigentes.');

      // 3. Agregar segunda obra social (Swiss Medical · SMG 20) y marcarla como predeterminada
      await seleccionarOpcionPorTexto(formCobertura.locator('select[name="idPlan"]'), /Swiss Medical.*SMG 20/);
      await formCobertura.locator('input[name="nroBeneficiario"]').fill('990022');
      await formCobertura.getByRole('button', { name: 'Agregar' }).click();

      await expect(page.locator('body')).toContainText('Obra social agregada correctamente.');

      const filaSwiss = page
        .locator('main section')
        .filter({ has: page.getByRole('heading', { name: 'Obras sociales', exact: true }) })
        .locator('div.flex.flex-wrap.items-center.justify-between')
        .filter({ hasText: 'Swiss Medical' })
        .first();
      await filaSwiss.getByRole('button', { name: 'Marcar como predeterminada' }).click();
      await expect(page.locator('body')).toContainText('La obra social quedó marcada como predeterminada.');

      // 4. Dar de baja OSDE 410 confirmando en #dialogo-confirmar
      const filaOsde = page
        .locator('main section')
        .filter({ has: page.getByRole('heading', { name: 'Obras sociales', exact: true }) })
        .locator('div.flex.flex-wrap.items-center.justify-between')
        .filter({ hasText: 'OSDE' })
        .first();
      await filaOsde.getByRole('button', { name: 'Dar de baja' }).click();

      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await expect(dialogo.locator('#dialogo-confirmar-titulo')).toContainText('Dar de baja «OSDE»');
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page.locator('body')).toContainText('La obra social quedó dada de baja.');
      await expect(page.getByRole('heading', { name: 'Obras sociales anteriores' })).toBeVisible();
    });

    await test.step('Verificar en /admin/auditoria (como admin) los registros realizados por gonzalez', async () => {
      const ctxAdmin = await browser.newContext();
      try {
        const pageAdmin = await ctxAdmin.newPage();
        await loginStaff(pageAdmin, 'admin', 'admin1234');
        await pageAdmin.goto('/admin/auditoria');

        const filaAltaPaciente = pageAdmin
          .locator('table tbody tr')
          .filter({ hasText: 'gonzalez' })
          .filter({ hasText: 'Mendoza, Lucas' })
          .first();
        await expect(filaAltaPaciente).toBeVisible();

        const filaBajaCobertura = pageAdmin
          .locator('table tbody tr')
          .filter({ hasText: 'gonzalez' })
          .filter({ hasText: 'Baja' })
          .filter({ hasText: 'Cobertura de Mendoza, Lucas' })
          .first();
        await expect(filaBajaCobertura).toBeVisible();
      } finally {
        await ctxAdmin.close();
      }
    });
  });

  test('8. Permisos 403 en rutas ajenas y cierre de sesión (Logout)', async ({ page }) => {
    await loginStaff(page, 'gonzalez', 'demo1234');
    await expect(page).toHaveURL(/\/dashboard$/);

    await test.step('Verificar HTTP 403 en paneles de otros roles, evaluación anestésica y /admin', async () => {
      const rutasProhibidas = [
        '/cirujano',
        '/cirujano/agenda',
        '/cirujano/historial',
        '/anestesista',
        '/anestesista/1/evaluar',
        '/direccion',
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

    await test.step('Cerrar sesión con Salir y comprobar redirección a /login al volver a /dashboard', async () => {
      await page.goto('/dashboard');
      await page.locator('header.sticky').getByRole('button', { name: 'Salir' }).click();
      await expect(page).toHaveURL(/\/login$/);

      await page.goto('/dashboard');
      await expect(page).toHaveURL(/\/login$/);
    });
  });
});
