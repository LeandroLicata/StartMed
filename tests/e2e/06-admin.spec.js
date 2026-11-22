import { test, expect } from '@playwright/test';
import { loginStaff, seleccionarOpcionPorTexto } from './helpers/auth.js';

const USUARIO_E2E = 'operador_e2e';
const DNI_USUARIO_E2E = '43112233';
const CLAVE_INICIAL_E2E = 'clave1234';
const CLAVE_NUEVA_E2E = 'NuevaClave99!';
const DNI_PERSONA_E2E = '43445566';

test.describe('Rol Administrador (admin)', () => {
  test('aterriza en /admin con sidebar exclusivo, accede como comodín al staff y queda aislado de /portal', async ({ page }) => {
    await test.step('Iniciar sesión como admin y verificar aterrizaje en /admin', async () => {
      await loginStaff(page, 'admin', 'admin1234');
      await expect(page).toHaveURL(/\/admin$/);
      await expect(page.getByRole('heading', { name: 'Catálogos', level: 1 })).toBeVisible();

      for (const grupo of [
        'Personas y acceso',
        'Cirugías',
        'Quirófanos',
        'Cobertura',
        'Materiales',
        'Hemoderivados',
        'Anestesia',
        'Preparación',
        'Profilaxis',
      ]) {
        await expect(page.getByRole('heading', { name: grupo, exact: true })).toBeVisible();
      }
    });

    await test.step('Comprobar que el sidebar muestra únicamente los 7 ítems de Administración', async () => {
      const aside = page.locator('aside').first();
      for (const item of [
        'Catálogos',
        'Usuarios',
        'Personas',
        'Consentimientos',
        'Cuestionario preanestésico',
        'Proveedores y precios',
        'Auditoría',
      ]) {
        await expect(aside.getByRole('link', { name: item, exact: true })).toBeVisible();
      }

      for (const ajeno of ['Tablero', 'Agenda', 'Pacientes', 'Panel de dirección', 'Mis cirugías']) {
        await expect(aside.getByRole('link', { name: ajeno, exact: true })).toHaveCount(0);
      }
    });

    await test.step('Verificar acceso comodín de admin a los paneles operativos del staff', async () => {
      for (const ruta of ['/dashboard', '/direccion', '/cirujano', '/anestesista']) {
        const respuesta = await page.goto(ruta);
        expect(respuesta?.status()).toBe(200);
        await expect(page).toHaveURL(new RegExp(`${ruta}$`));
      }
    });

    await test.step('Confirmar aislamiento del portal del paciente (/portal redirige a /portal/ingresar)', async () => {
      await page.goto('/portal');
      await expect(page).toHaveURL(/\/portal\/ingresar$/);
    });
  });

  test('catálogos maestros: CRUD completo en tipo-estudio, protección de DNI y baja lógica de Pasaporte', async ({ page }) => {
    test.slow();
    await loginStaff(page, 'admin', 'admin1234');

    const nombreAlta = 'Resonancia funcional E2E';
    const nombreEditado = 'Resonancia funcional E2E Editada';

    await test.step('Dar de alta un nuevo Tipo de estudio', async () => {
      await page.goto('/admin/catalogos/tipo-estudio');
      await expect(page.getByRole('heading', { name: 'Tipos de estudio', level: 1 })).toBeVisible();

      await page.getByRole('link', { name: 'Nuevo', exact: true }).click();
      await expect(page).toHaveURL(/\/admin\/catalogos\/tipo-estudio\/nuevo$/);

      await page.locator('input[name="nombreTipoEstudio"]').fill(nombreAlta);
      await page.getByRole('button', { name: 'Crear', exact: true }).click();

      await expect(page).toHaveURL(/\/admin\/catalogos\/tipo-estudio/);
      await expect(page.locator('body')).toContainText('Tipo de estudio creado correctamente.');
      await expect(page.locator('table tbody tr').filter({ hasText: nombreAlta })).toBeVisible();
    });

    await test.step('Editar el Tipo de estudio recién creado', async () => {
      const fila = page.locator('table tbody tr').filter({ hasText: nombreAlta }).first();
      await fila.getByRole('link', { name: 'Editar' }).click();

      await page.locator('input[name="nombreTipoEstudio"]').fill(nombreEditado);
      await page.getByRole('button', { name: 'Guardar cambios' }).click();

      await expect(page).toHaveURL(/\/admin\/catalogos\/tipo-estudio/);
      await expect(page.locator('body')).toContainText('Tipo de estudio actualizado correctamente.');
      await expect(page.locator('table tbody tr').filter({ hasText: nombreEditado })).toBeVisible();
    });

    await test.step('Dar de baja lógica el Tipo de estudio confirmando en #dialogo-confirmar y luego reactivarlo', async () => {
      const fila = page.locator('table tbody tr').filter({ hasText: nombreEditado }).first();
      await fila.getByRole('button', { name: 'Dar de baja' }).click();

      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await expect(dialogo.locator('#dialogo-confirmar-titulo')).toContainText(`Dar de baja «${nombreEditado}»`);
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page.locator('body')).toContainText('Tipo de estudio dado de baja.');
      await expect(page.locator('table tbody tr').filter({ hasText: nombreEditado })).toHaveCount(0);

      await page.locator('select[name="estado"]').selectOption('bajas');
      await page.getByRole('button', { name: 'Filtrar' }).click();

      const filaBaja = page.locator('table tbody tr').filter({ hasText: nombreEditado }).first();
      await expect(filaBaja).toBeVisible();
      await expect(filaBaja).toContainText('Dado de baja');

      await filaBaja.getByRole('button', { name: 'Reactivar' }).click();
      await expect(page.locator('body')).toContainText('Tipo de estudio reactivado.');

      await page.goto('/admin/catalogos/tipo-estudio');
      await expect(page.locator('table tbody tr').filter({ hasText: nombreEditado })).toBeVisible();
    });

    await test.step('Verificar protección del registro del sistema DNI en tipo-documento', async () => {
      await page.goto('/admin/catalogos/tipo-documento');
      await expect(page.getByRole('heading', { name: 'Tipos de documento', level: 1 })).toBeVisible();

      const filaDni = page.locator('table tbody tr').filter({ hasText: 'DNI' }).first();
      await expect(filaDni).toContainText('Del sistema');
      await expect(filaDni.getByRole('button', { name: 'Dar de baja' })).toHaveCount(0);

      await filaDni.getByRole('link', { name: 'Editar' }).click();
      await expect(page.locator('body')).toContainText('Registro protegido');
      await expect(page.getByRole('button', { name: 'Guardar cambios' })).toHaveCount(0);
    });

    await test.step('Dar de baja lógica a Pasaporte en tipo-documento confirmando en #dialogo-confirmar', async () => {
      await page.goto('/admin/catalogos/tipo-documento');
      const filaPasaporte = page.locator('table tbody tr').filter({ hasText: 'Pasaporte' }).first();
      await expect(filaPasaporte).toBeVisible();
      await expect(filaPasaporte).toContainText('Activo');

      await filaPasaporte.getByRole('button', { name: 'Dar de baja' }).click();

      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await expect(dialogo.locator('#dialogo-confirmar-titulo')).toContainText('Dar de baja «Pasaporte»');
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page).toHaveURL(/\/admin\/catalogos\/tipo-documento/);
      await expect(page.locator('body')).toContainText('Tipo de documento dado de baja.');
      await expect(page.locator('table tbody tr').filter({ hasText: 'Pasaporte' })).toHaveCount(0);

      await page.locator('select[name="estado"]').selectOption('bajas');
      await page.getByRole('button', { name: 'Filtrar' }).click();

      const filaBaja = page.locator('table tbody tr').filter({ hasText: 'Pasaporte' }).first();
      await expect(filaBaja).toBeVisible();
      await expect(filaBaja).toContainText('Dado de baja');
    });
  });

  test('usuarios: validaciones negativas, alta, edición de roles, cambio de clave, baja/reactivación y autoprotección', async ({ page }) => {
    test.slow();
    await loginStaff(page, 'admin', 'admin1234');

    await test.step('Rechazar alta con nombreUsuario duplicado y contraseña corta', async () => {
      await page.goto('/admin/usuarios/nuevo');
      await expect(page.getByRole('heading', { name: 'Nuevo usuario', level: 1 })).toBeVisible();

      await seleccionarOpcionPorTexto(page.locator('select[name="tipo_documento_id"]'), /^DNI$/i);
      await page.locator('input[name="documento"]').fill(DNI_USUARIO_E2E);
      await page.locator('input[name="apellidos"]').fill('Quiroga');
      await page.locator('input[name="nombres"]').fill('Martín');
      await page.locator('input[name="contacto_email_direccion"]').fill('operador.e2e@startmed.test');
      await page.locator('input[name="telefono_numero"]').fill('2614998877');
      await page.locator('input[name="direccionPersona"]').fill('San Martín 1234, Mendoza');
      await page.locator('input[name="nombreUsuario"]').fill('admin');
      await page.locator('input[name="password"]').fill('123');
      await page.locator('input[name="password_confirmation"]').fill('123');
      await page.locator('label').filter({ hasText: 'Gestor de quirófano' }).locator('input[type="checkbox"]').check();

      await page.getByRole('button', { name: 'Crear usuario' }).click();

      await expect(page).toHaveURL(/\/admin\/usuarios\/nuevo$/);
      await expect(page.locator('body')).toContainText(/en uso|registrado|al menos|caracteres/i);
    });

    await test.step('Crear usuario nuevo operador_e2e y verificarlo en el listado', async () => {
      await page.locator('input[name="nombreUsuario"]').fill(USUARIO_E2E);
      await page.locator('input[name="password"]').fill(CLAVE_INICIAL_E2E);
      await page.locator('input[name="password_confirmation"]').fill(CLAVE_INICIAL_E2E);
      await page.getByRole('button', { name: 'Crear usuario' }).click();

      await expect(page).toHaveURL(/\/admin\/usuarios/);
      await expect(page.locator('body')).toContainText(`Usuario ${USUARIO_E2E} creado correctamente.`);
      await expect(page.locator('table tbody tr').filter({ hasText: USUARIO_E2E })).toBeVisible();
    });

    await test.step('Editar roles y cambiar contraseña del usuario operador_e2e', async () => {
      const fila = page.locator('table tbody tr').filter({ hasText: USUARIO_E2E }).first();
      await fila.getByRole('link', { name: 'Editar' }).click();

      await page.locator('label').filter({ hasText: 'Dirección médica' }).locator('input[type="checkbox"]').check();
      await page.getByRole('button', { name: 'Guardar cambios' }).click();

      await expect(page).toHaveURL(/\/admin\/usuarios/);
      await expect(page.locator('body')).toContainText(`Usuario ${USUARIO_E2E} actualizado correctamente.`);

      const filaActualizada = page.locator('table tbody tr').filter({ hasText: USUARIO_E2E }).first();
      await expect(filaActualizada).toContainText('Dirección médica');
      await filaActualizada.getByRole('link', { name: 'Editar' }).click();

      await page.locator('input[name="password"]').fill(CLAVE_NUEVA_E2E);
      await page.locator('input[name="password_confirmation"]').fill(CLAVE_NUEVA_E2E);
      await page.getByRole('button', { name: 'Cambiar contraseña' }).click();

      await expect(page).toHaveURL(/\/admin\/usuarios/);
      await expect(page.locator('body')).toContainText(`Contraseña de ${USUARIO_E2E} actualizada.`);
    });

    await test.step('Dar de baja lógica y reactivar a operador_e2e', async () => {
      await page.goto('/admin/usuarios');
      const fila = page.locator('table tbody tr').filter({ hasText: USUARIO_E2E }).first();
      await fila.getByRole('button', { name: 'Dar de baja' }).click();

      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page.locator('body')).toContainText(`Usuario ${USUARIO_E2E} dado de baja.`);
      await expect(page.locator('table tbody tr').filter({ hasText: USUARIO_E2E })).toHaveCount(0);

      await page.locator('select[name="estado"]').selectOption('bajas');
      await page.getByRole('button', { name: 'Buscar' }).click();

      const filaBaja = page.locator('table tbody tr').filter({ hasText: USUARIO_E2E }).first();
      await expect(filaBaja).toBeVisible();
      await filaBaja.getByRole('button', { name: 'Reactivar' }).click();

      await expect(page.locator('body')).toContainText(`Usuario ${USUARIO_E2E} reactivado.`);
    });

    await test.step('Comprobar autoprotección: admin no puede darse de baja ni quitarse el rol Administrador', async () => {
      await page.goto('/admin/usuarios');

      const filaAdmin = page.locator('table tbody tr').filter({ hasText: 'admin' }).first();
      await filaAdmin.getByRole('button', { name: 'Dar de baja' }).click();

      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page.locator('body')).toContainText('No podés darte de baja a vos mismo.');
      await expect(page.locator('table tbody tr').filter({ hasText: 'admin' }).first()).toBeVisible();

      await page.locator('table tbody tr').filter({ hasText: 'admin' }).first().getByRole('link', { name: 'Editar' }).click();
      // El usuario admin sembrado puede tener campos de contacto vacíos que bloquean el submit HTML5
      if (!(await page.locator('input[name="contacto_email_direccion"]').inputValue())) {
        await page.locator('input[name="contacto_email_direccion"]').fill('admin@hospital.uncuyo.edu.ar');
      }
      if (!(await page.locator('input[name="telefono_numero"]').inputValue())) {
        await page.locator('input[name="telefono_numero"]').fill('2614135000');
      }
      if (!(await page.locator('input[name="direccionPersona"]').inputValue())) {
        await page.locator('input[name="direccionPersona"]').fill('Paso de los Andes 3051, Mendoza');
      }
      await page.locator('label').filter({ hasText: 'Administrador' }).locator('input[type="checkbox"]').uncheck();
      await page.getByRole('button', { name: 'Guardar cambios' }).click();

      await expect(page.locator('body')).toContainText('No podés quitarte el rol Administrador');
    });
  });

  test('personas: filtros, alta, edición, enlace de promoción a usuario, baja lógica y reactivación', async ({ page }) => {
    test.slow();
    await loginStaff(page, 'admin', 'admin1234');

    await test.step('Probar filtro por tipo de persona en /admin/personas', async () => {
      await page.goto('/admin/personas');
      await expect(page.getByRole('heading', { name: 'Personas', level: 1 })).toBeVisible();

      await page.locator('select[name="tipo"]').selectOption('paciente');
      await page.locator('input[name="q"]').fill('28456789');
      await page.getByRole('button', { name: 'Buscar' }).click();

      await expect(page.locator('table tbody tr').filter({ hasText: '28456789' })).toBeVisible();
      await page.getByRole('link', { name: 'Limpiar' }).click();
    });

    await test.step('Dar de alta una nueva persona y verificar enlace de promoción a usuario', async () => {
      await page.getByRole('link', { name: 'Nueva persona' }).click();
      await expect(page).toHaveURL(/\/admin\/personas\/nueva$/);

      await seleccionarOpcionPorTexto(page.locator('select[name="tipo_documento_id"]'), /^DNI$/i);
      await page.locator('input[name="documento"]').fill(DNI_PERSONA_E2E);
      await page.locator('input[name="apellidos"]').fill('Ferreira');
      await page.locator('input[name="nombres"]').fill('Sofía');
      await page.locator('input[name="contacto_email_direccion"]').fill('sofia.ferreira@startmed.test');
      await page.locator('input[name="telefono_numero"]').fill('2614887766');
      await page.locator('input[name="direccionPersona"]').fill('Belgrano 450, Mendoza');

      await page.getByRole('button', { name: 'Crear persona' }).click();

      await expect(page).toHaveURL(/\/admin\/personas/);
      await expect(page.locator('body')).toContainText('Persona Ferreira, Sofía creada correctamente.');

      await page.locator('input[name="q"]').fill(DNI_PERSONA_E2E);
      await page.getByRole('button', { name: 'Buscar' }).click();

      const fila = page.locator('table tbody tr').filter({ hasText: DNI_PERSONA_E2E }).first();
      await expect(fila).toBeVisible();
      await expect(fila).toContainText('Ferreira, Sofía');

      const linkPromover = fila.getByRole('link', { name: 'Crear usuario' });
      await expect(linkPromover).toBeVisible();
      await expect(linkPromover).toHaveAttribute('href', /\/admin\/usuarios\/nuevo\?persona=\d+/);
    });

    await test.step('Editar la persona y luego darla de baja lógica y reactivarla', async () => {
      const fila = page.locator('table tbody tr').filter({ hasText: DNI_PERSONA_E2E }).first();
      await fila.getByRole('link', { name: 'Editar' }).click();

      await page.locator('input[name="nombres"]').fill('Sofía Belén');
      await page.getByRole('button', { name: 'Guardar cambios' }).click();

      await expect(page.locator('body')).toContainText('Persona Ferreira, Sofía Belén actualizada correctamente.');

      await page.locator('input[name="q"]').fill(DNI_PERSONA_E2E);
      await page.getByRole('button', { name: 'Buscar' }).click();

      const filaEditada = page.locator('table tbody tr').filter({ hasText: DNI_PERSONA_E2E }).first();
      await expect(filaEditada).toContainText('Ferreira, Sofía Belén');

      await filaEditada.getByRole('button', { name: 'Dar de baja' }).click();
      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page.locator('body')).toContainText('Persona Ferreira, Sofía Belén dada de baja.');

      await page.locator('input[name="q"]').fill(DNI_PERSONA_E2E);
      await page.locator('select[name="estado"]').selectOption('bajas');
      await page.getByRole('button', { name: 'Buscar' }).click();

      const filaBaja = page.locator('table tbody tr').filter({ hasText: DNI_PERSONA_E2E }).first();
      await expect(filaBaja).toBeVisible();
      await filaBaja.getByRole('button', { name: 'Reactivar' }).click();

      await expect(page.locator('body')).toContainText('Persona Ferreira, Sofía Belén reactivada.');
    });
  });

  test('consentimientos informados: rechazar marcador desconocido, publicar versión válida, corregirla e inmutabilidad de firmadas', async ({ page }) => {
    test.slow();
    await loginStaff(page, 'admin', 'admin1234');

    await test.step('Abrir historial de Artrodesis lumbar e intentar publicar marcador desconocido {{invalido}}', async () => {
      await page.goto('/admin/consentimientos');
      await expect(page.getByRole('heading', { name: 'Consentimientos informados', level: 1 })).toBeVisible();

      const filaArtrodesis = page.locator('table tbody tr').filter({ hasText: 'Artrodesis lumbar' }).first();
      await expect(filaArtrodesis).toBeVisible();
      await filaArtrodesis.getByRole('link', { name: 'Ver versiones' }).click();

      await page.getByRole('link', { name: 'Nueva versión' }).click();
      await page
        .locator('textarea[name="textoConfigConsentimiento"]')
        .fill('Yo {{paciente}} con {{invalido}} acepto el procedimiento {{procedimiento}}.');

      await page.getByRole('button', { name: 'Publicar versión' }).click();

      await expect(page).toHaveURL(/\/nueva$/);
      await expect(page.locator('body')).toContainText('{{invalido}}');
    });

    await test.step('Publicar nueva versión válida con marcadores soportados y corregirla mientras no tiene firmas', async () => {
      const textoValido =
        'Consentimiento E2E: Yo {{paciente}} (DNI {{dni}}) autorizo la cirugía {{procedimiento}} a cargo de {{cirujano}}.';
      await page.locator('textarea[name="textoConfigConsentimiento"]').fill(textoValido);
      await page.getByRole('button', { name: 'Publicar versión' }).click();

      await expect(page.locator('body')).toContainText('Nueva versión publicada.');
      await expect(page.locator('body')).toContainText('Consentimiento E2E:');

      await page.getByRole('link', { name: 'Corregir' }).first().click();
      await page
        .locator('textarea[name="textoConfigConsentimiento"]')
        .fill(`${textoValido} Revisión E2E aprobada.`);
      await page.getByRole('button', { name: 'Guardar corrección' }).click();

      await expect(page.locator('body')).toContainText('Versión corregida.');
      await expect(page.locator('body')).toContainText('Revisión E2E aprobada.');
    });

    await test.step('Verificar que una plantilla con consentimientos ya firmados no ofrece botón Corregir', async () => {
      await page.goto('/admin/consentimientos');
      const filaColecistectomia = page.locator('table tbody tr').filter({ hasText: 'Colecistectomía laparoscópica' }).first();
      await expect(filaColecistectomia).toBeVisible();
      await filaColecistectomia.getByRole('link', { name: 'Ver versiones' }).click();

      // En Colecistectomía laparoscópica la versión vigente ya tiene firmas del seed / 01-paciente
      await expect(page.locator('body')).toContainText(/firmad[oa]s?/i);
      await expect(page.getByRole('link', { name: 'Corregir' })).toHaveCount(0);
    });
  });

  test('proveedores y precios: búsqueda, actualización de precio, disponibilidad, alta/baja de unidad y acceso a cuestionario', async ({ page }) => {
    test.slow();
    await loginStaff(page, 'admin', 'admin1234');

    await test.step('Buscar un material en /admin/precios y abrir su ficha de proveedores', async () => {
      await page.goto('/admin/precios');
      await expect(page.getByRole('heading', { name: 'Proveedores y precios', level: 1 })).toBeVisible();

      const filaConProveedor = page
        .locator('table tbody tr')
        .filter({ has: page.getByRole('link', { name: 'Ver proveedores' }) })
        .first();
      await expect(filaConProveedor).toBeVisible();

      const nombreMaterial = (await filaConProveedor.locator('td').first().innerText()).trim();
      await page.locator('input[name="q"]').fill(nombreMaterial);
      await page.getByRole('button', { name: 'Buscar' }).click();

      await expect(page.locator('table tbody tr').filter({ hasText: nombreMaterial })).toBeVisible();
      await page.locator('table tbody tr').filter({ hasText: nombreMaterial }).first().getByRole('link', { name: 'Ver proveedores' }).click();

      await expect(page.getByRole('heading', { name: nombreMaterial, level: 1 })).toBeVisible();
    });

    await test.step('Actualizar precio unitario y alternar disponibilidad de una unidad existente', async () => {
      const formPrecio = page.locator('form').filter({ has: page.getByRole('button', { name: 'Guardar' }) }).first();
      await formPrecio.locator('input[name="precioExternoMaterialProveedorTipoMedida"]').fill('125.50');
      await formPrecio.getByRole('button', { name: 'Guardar' }).click();

      await expect(page.locator('body')).toContainText('Precio actualizado.');
      await expect(
        page
          .locator('form')
          .filter({ has: page.getByRole('button', { name: 'Guardar' }) })
          .first()
          .locator('input[name="precioExternoMaterialProveedorTipoMedida"]')
      ).toHaveValue('125.50');

      const btnDisp = page.getByRole('button', { name: 'Cambiar disponibilidad' }).first();
      await btnDisp.click();
      await expect(page.locator('body')).toContainText(/Unidad marcada como (no )?disponible\./);

      await page.getByRole('button', { name: 'Cambiar disponibilidad' }).first().click();
      await expect(page.locator('body')).toContainText(/Unidad marcada como (no )?disponible\./);
    });

    await test.step('Agregar una unidad de venta y luego darla de baja lógica', async () => {
      const tarjetaProveedor = page.locator('section').filter({ hasText: 'Unidades de venta' }).first();
      const existentes = (await tarjetaProveedor.locator('p.text-sm.font-semibold.text-hu-azul').allInnerTexts()).map((t) =>
        t.trim()
      );

      const formNueva = tarjetaProveedor.locator('form').filter({ has: page.getByRole('button', { name: 'Agregar unidad' }) }).first();
      const selectUnidad = formNueva.locator('select[name="idTipoMedida"]');

      const opciones = await selectUnidad.locator('option').all();
      let valorElegido = '';
      for (const opt of opciones) {
        const val = await opt.getAttribute('value');
        const texto = (await opt.innerText()).trim();
        if (val && !existentes.includes(texto)) {
          valorElegido = val;
          break;
        }
      }
      expect(valorElegido).not.toBe('');

      await selectUnidad.selectOption(valorElegido);
      await formNueva.locator('input[name="codExternoMaterialProveedorTipoMedida"]').fill('E2E-PRE-01');
      await formNueva.locator('input[name="precioExternoMaterialProveedorTipoMedida"]').fill('49.90');
      await formNueva.getByRole('button', { name: 'Agregar unidad' }).click();

      await expect(page.locator('body')).toContainText('Unidad agregada.');

      const filaNueva = page
        .locator('div.rounded-xl')
        .filter({ has: page.locator('input[value="E2E-PRE-01"]') })
        .first();
      await expect(filaNueva).toBeVisible();

      await filaNueva.getByRole('button', { name: 'Dar de baja la unidad' }).click();
      const dialogo = page.locator('#dialogo-confirmar');
      await expect(dialogo).toBeVisible();
      await dialogo.locator('[data-dialogo-aceptar]').click();

      await expect(page.locator('body')).toContainText('Unidad dada de baja. Queda en el historial.');
    });

    await test.step('Confirmar acceso de admin al Cuestionario preanestésico (/admin/cuestionario)', async () => {
      await page.goto('/admin/cuestionario');
      await expect(page.getByRole('heading', { name: 'Cuestionario preanestésico', level: 1 })).toBeVisible();
      await expect(page.locator('table tbody tr').filter({ hasText: 'En uso' })).toBeVisible();
    });
  });

  test('auditoría (/admin/auditoria): trazabilidad de acciones sin exponer contraseñas, filtros y logout', async ({ page }) => {
    await loginStaff(page, 'admin', 'admin1234');

    await test.step('Verificar en /admin/auditoria la baja de Pasaporte y el cambio de clave sin exponer texto plano', async () => {
      await page.goto('/admin/auditoria');
      await page.reload();
      await expect(page.getByRole('heading', { name: 'Auditoría', level: 1 })).toBeVisible();

      const filaPasaporte = page
        .locator('table tbody tr')
        .filter({ hasText: 'Tipo de documento «Pasaporte»' })
        .first();
      await expect(filaPasaporte).toBeVisible();
      await expect(filaPasaporte).toContainText('admin');
      await expect(filaPasaporte).toContainText('Baja');

      // Confirmar que la nueva contraseña jamás aparece en el registro de auditoría
      await expect(page.locator('body')).not.toContainText(CLAVE_NUEVA_E2E);
    });

    await test.step('Filtrar auditoría por acción Cambio de contraseña y limpiar filtros', async () => {
      await seleccionarOpcionPorTexto(page.locator('select[name="accion"]'), /Cambio de contraseña/i);
      await page.getByRole('button', { name: 'Filtrar' }).click();

      const filaClave = page.locator('table tbody tr').filter({ hasText: USUARIO_E2E }).first();
      await expect(filaClave).toBeVisible();
      await expect(filaClave).toContainText('Cambio de contraseña');
      await expect(page.locator('body')).not.toContainText(CLAVE_NUEVA_E2E);

      await page.getByRole('link', { name: 'Limpiar' }).click();
      await expect(page).toHaveURL(/\/admin\/auditoria$/);
    });

    await test.step('Cerrar sesión desde el header y verificar protección de /admin', async () => {
      await page.locator('header.sticky').getByRole('button', { name: 'Salir' }).click();
      await expect(page).toHaveURL(/\/login$/);

      await page.goto('/admin');
      await expect(page).toHaveURL(/\/login$/);
    });
  });
});
