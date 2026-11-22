Tablero (`/dashboard`): indicadores del día (cirugías de hoy, quirófanos habilitados, pacientes listos, procesos completos, casos en riesgo, sin autorización aprobada, con estudios sin subir), la agenda de hoy agrupada por quirófano con el semáforo de cada caso, y las cirugías de la semana con filtros.

Cirugías (`/cirugias`): todas las cirugías, pasadas y futuras, con filtros por texto, estado, quirófano, obra social y rango de fechas. Desde ahí está el botón Nueva cirugía.

Nueva cirugía (`/cirugias/nueva`), paso a paso:
1. Buscar al paciente por DNI o apellido. Si no existe, darlo de alta ahí mismo (apellidos, nombres, DNI y, opcionalmente, fecha de nacimiento, género, email y teléfono) con «Dar de alta y continuar».
2. Cobertura: usar una obra social ya cargada del paciente (viene preseleccionada la predeterminada), cargar una obra social nueva (plan y número de beneficiario opcional) o marcar Particular, sin obra social.
3. Datos de la cirugía: tipo de procedimiento, quirófano, fecha y hora de inicio, hora estimada de fin (opcional), cirujano y anestesista (opcionales). Se puede tildar Requiere implante, Requiere hemoderivados y Requiere hisopado SAMR; el detalle de esos pedidos se carga después en el expediente.
4. «Comprobar disponibilidad»: verifica que el quirófano, el cirujano y el anestesista estén libres en ese horario. «Crear cirugía» se habilita solo cuando todo está disponible.
La cirugía nace En espera si tiene cirujano y anestesista, o En espera de confirmación si falta alguno.

Agenda (`/agenda`): ocupación de los quirófanos en vista de mes, semana o día.

En el expediente, el gestor además puede:
- Reprogramar (botón en la cabecera): elegir nueva fecha, hora de inicio y fin, quirófano y cobertura. La cirugía original queda Reprogramada y se crea una nueva con los mismos estudios, pedidos y datos.
- Cancelar la cirugía (menú de tres puntos de la cabecera): pasa a Suspendida y libera el quirófano y el equipo. No se registra un motivo de suspensión.
- Activar implante o requerir hemoderivados o hisopado SAMR (mismo menú).
- Reasignar profesional desde la tarjeta Equipo quirúrgico del Resumen, que muestra solo profesionales libres en ese horario y guarda el historial de asignaciones.
- Todo lo de las demás solapas: estudios, materiales, hemoderivados, profilaxis, autorización y preparación.

Pacientes (`/pacientes`): padrón de personas registradas, con búsqueda por nombre, apellido o DNI. El gestor puede dar de alta un paciente con el botón Nuevo paciente (`/pacientes/nuevo`) y editar sus datos: documento, nombres y apellidos, fecha de nacimiento, género, grupo sanguíneo, contacto, dirección, observaciones, si es crónico y si no acepta transfusiones. La ficha de cada paciente muestra su historial de cirugías y sus obras sociales: ahí se agrega una cobertura (obra social, plan y número de afiliado), se marca la predeterminada o se da de baja una, que pasa a obras sociales anteriores. El paciente, desde su portal, solo puede elegir cuál es su predeterminada; agregar o dar de baja coberturas lo hace el gestor.

Los mensajes que los pacientes mandan desde la pestaña Contacto de su portal llegan por email a los gestores de quirófano.
