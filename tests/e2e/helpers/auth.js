import { expect } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

// Cache de cookies de sesión por usuario (en memoria y en archivo temporal) para
// sobrevivir reinicios de worker y evitar disparar `throttle:6,1` en POST /login.
const ARCHIVO_SESIONES = path.join(os.tmpdir(), 'startmed-e2e-sessions.json');
const sesionesStaff = cargarSesiones();

function cargarSesiones() {
  try {
    const raw = fs.readFileSync(ARCHIVO_SESIONES, 'utf8');
    return new Map(Object.entries(JSON.parse(raw)));
  } catch {
    return new Map();
  }
}

function guardarSesiones() {
  try {
    fs.writeFileSync(ARCHIVO_SESIONES, JSON.stringify(Object.fromEntries(sesionesStaff)));
  } catch {
    // Si no se puede escribir en tmpdir, sigue funcionando la cache en memoria.
  }
}

/**
 * Inicia sesión en el panel del staff (/login) con credenciales de Usuario.
 * Si el mismo usuario ya inició sesión en esta corrida, reutiliza su cookie de sesión
 * en el nuevo contexto y navega a `/` (que redirige a su ruta inicial).
 */
export async function loginStaff(page, nombreUsuario, password) {
  const clave = nombreUsuario.trim().toLowerCase();

  if (sesionesStaff.has(clave)) {
    await page.context().addCookies(sesionesStaff.get(clave));
    await page.goto('/');
    if (!/\/login$/.test(page.url())) {
      return;
    }
    sesionesStaff.delete(clave);
    guardarSesiones();
  }

  await page.goto('/login');
  await page.locator('input[name="nombreUsuario"]').fill(nombreUsuario);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Ingresar' }).click();
  await expect(page).not.toHaveURL(/\/login$/);

  sesionesStaff.set(clave, await page.context().cookies());
  guardarSesiones();
}

/**
 * Inicia sesión en el portal del paciente usando el atajo local (/portal/entrar-local).
 */
export async function loginPacienteLocal(page, documento = null) {
  const url = documento ? `/portal/entrar-local/${documento}` : '/portal/entrar-local';
  await page.goto(url);
  await expect(page).toHaveURL(/\/portal/);
}

/**
 * Lee el valor numérico de una tarjeta <x-metrica> por su etiqueta exacta.
 */
export async function leerMetrica(page, etiqueta) {
  const tarjeta = page
    .locator('div.rounded-2xl')
    .filter({ has: page.locator('p', { hasText: new RegExp(`^\\s*${escaparRegex(etiqueta)}\\s*$`) }) })
    .first();

  await expect(tarjeta).toBeVisible();
  const texto = (await tarjeta.locator('p.text-3xl').innerText()).trim();
  return Number.parseInt(texto, 10);
}

/**
 * Selecciona una opción de un <select> cuyo texto visible coincida con un patrón.
 */
export async function seleccionarOpcionPorTexto(selectLocator, patron) {
  const valor = await selectLocator.locator('option').evaluateAll((opciones, regexFuente) => {
    const regex = new RegExp(regexFuente, 'i');
    const encontrada = opciones.find((opt) => !opt.disabled && opt.value !== '' && regex.test((opt.textContent ?? '').trim()));
    return encontrada ? encontrada.value : null;
  }, patron.source);

  expect(valor, `No se encontró ninguna opción que coincida con ${patron}`).not.toBeNull();
  await selectLocator.selectOption(valor);
}

/**
 * Carga un campo datetime-local manejando tanto Flatpickr (si cargó desde CDN)
 * como el input nativo de HTML5 (si no cargó Flatpickr).
 */
export async function fijarFechaHora(page, nombreInput, valorISO) {
  const input = page.locator(`input[name="${nombreInput}"]`);
  await input.waitFor({ state: 'attached' });

  const usoFlatpickr = await input.evaluate((el, val) => {
    if (el._flatpickr) {
      el._flatpickr.setDate(val, true, 'Y-m-d\\TH:i');
      return true;
    }
    return false;
  }, valorISO);

  if (!usoFlatpickr) {
    await input.fill(valorISO);
  }
}

/**
 * Crea una cirugía completa desde /cirugias/nueva pasando por búsqueda de paciente,
 * comprobación de disponibilidad y guardado. Devuelve el ID de la cirugía creada.
 */
export async function crearCirugiaDesdeUI(page, {
  dniPaciente,
  tipoCirugia,
  quirofano,
  fechaHoraInicio,
  cirujano = null,
  anestesista = null,
  cobertura = 'existente',
}) {
  await page.goto('/cirugias/nueva');

  // Paso 1: buscar y seleccionar paciente en el dropdown reactivo
  const buscador = page.locator('#input-buscar-paciente');
  await expect(buscador).toBeVisible();
  await buscador.fill(dniPaciente);

  const sugerencia = page
    .locator('#dropdown-pacientes-contenido > div')
    .filter({ hasText: dniPaciente })
    .first();
  await expect(sugerencia).toBeVisible();
  await sugerencia.click();

  await expect(page).toHaveURL(/\/cirugias\/nueva\?persona=\d+/);

  // Paso 2: completar cobertura, procedimiento, quirófano, horario y equipo
  await page.locator(`input[name="cobertura"][value="${cobertura}"]`).check();
  await seleccionarOpcionPorTexto(page.locator('select[name="idTipoCirugia"]'), tipoCirugia);
  await seleccionarOpcionPorTexto(page.locator('select[name="idQuirofano"]'), quirofano);
  await fijarFechaHora(page, 'fechaHoraCirugia', fechaHoraInicio);

  if (cirujano) {
    await seleccionarOpcionPorTexto(page.locator('select[name="idPersonalCirujano"]'), cirujano);
  }
  if (anestesista) {
    await seleccionarOpcionPorTexto(page.locator('select[name="idPersonalAnestesista"]'), anestesista);
  }

  const btnCrear = page.getByRole('button', { name: 'Crear cirugía' });
  await expect(btnCrear).toBeDisabled();

  // Comprobar disponibilidad antes de habilitar "Crear cirugía"
  await page.getByRole('button', { name: 'Comprobar disponibilidad' }).click();

  await expect(page.locator('body')).toContainText('Resultado de la comprobación');
  await expect(page.locator('body')).toContainText('Quirófano: Disponible');
  await expect(btnCrear).toBeEnabled();

  await btnCrear.click();

  await expect(page).toHaveURL(/\/cirugias\/\d+$/);
  await expect(page.locator('body')).toContainText('Cirugía creada correctamente.');

  const match = page.url().match(/\/cirugias\/(\d+)$/);
  expect(match, `URL inesperada tras crear cirugía: ${page.url()}`).not.toBeNull();
  return match[1];
}

function escaparRegex(texto) {
  return texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
