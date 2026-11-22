Al entrar, cada rol cae en su propio panel: el administrador en Catálogos (`/admin`), el gestor de quirófano y la dirección médica en el Tablero (`/dashboard`), el cirujano en Mis cirugías (`/cirujano`) y el anestesista en Evaluaciones (`/anestesista`). Si alguien no tiene ningún rol asignado ve una pantalla que se lo explica; los roles los asigna un administrador desde Usuarios.

Quién hace qué:
- Gestor de quirófano: da de alta las cirugías, las reprograma y las cancela, maneja la agenda de quirófanos, da de alta y edita pacientes y sus obras sociales, y sigue que cada caso esté completo.
- Cirujano: ve sus cirugías, su agenda y su historial, y completa en el expediente estudios, materiales, hemoderivados, hisopado, profilaxis, preparación y autorización.
- Anestesista: hace la evaluación preanestésica de las cirugías que tiene asignadas y define el cuestionario preanestésico que responden los pacientes.
- Dirección médica: mira indicadores (volumen, suspensiones, uso de quirófanos) en Dirección y el Tablero.
- Administrador: carga los datos maestros (catálogos), el padrón de personas, los usuarios, las plantillas de consentimiento, los precios de materiales y revisa la auditoría.
- Paciente: no usa este sistema; tiene su propio portal en `/portal`, donde confirma asistencia, sube estudios, responde el cuestionario preanestésico y firma el consentimiento.

El expediente de una cirugía (se abre desde cualquier listado haciendo clic en la cirugía) tiene estas solapas: Resumen, Preparación, Estudios prequirúrgicos, Materiales y presupuesto, Hemoderivados, Profilaxis ATB / SAMR, Autorización financiador y Evaluación anestésica. Cada uno ve y edita lo que su rol permite; los botones que un rol no puede usar directamente no aparecen.

Una cirugía está lista cuando no le falta nada de esto: autorización de la obra social aprobada, todos los estudios prequirúrgicos subidos, evaluación anestésica completada, materiales aprobados (si requiere) y consentimiento firmado por el paciente. Lo que falta aparece como pendiente.

El semáforo de una cirugía: verde (Listo) si no le falta nada; rojo si le falta algo y faltan 2 días o menos; amarillo si le falta algo pero todavía hay margen.

Estados de una cirugía: En espera de confirmación (se creó sin cirujano o sin anestesista), En espera (equipo completo), Programada, Confirmada, Asistencia de paciente confirmada (el paciente confirmó desde el portal), En riesgo, A reprogramar, Reprogramada, Suspendida (cancelada) y Realizada. Cada cambio queda en el historial; no se pisa el estado anterior.

Pase automático a A reprogramar: cada hora el sistema revisa las cirugías de las próximas 24 horas; si a una le falta algo, la pasa a A reprogramar y libera el quirófano. También queda A reprogramar si el paciente, desde el portal, avisa que necesita reprogramar.

Archivos (resultados de estudios y del hisopado): se suben en PDF, JPG o PNG de hasta 20 MB. «Ver» abre el archivo en una pestaña nueva con un enlace que vence; si el archivo no está disponible, esa pestaña lo avisa y se puede cerrar. Subir un archivo nuevo reemplaza al anterior en pantalla.

«Ver como el paciente», en el expediente, muestra lo que esa persona ve en su portal.

Las acciones destructivas (cancelar, dar de baja, reemplazar un archivo) piden confirmación en un cuadro antes de hacerse. Casi nada se borra: lo que se da de baja se puede reactivar.

Si un formulario quedó abierto mucho tiempo, al enviarlo puede aparecer el aviso de que la página venció por seguridad: hay que volver a intentarlo. La contraseña no se recupera por email: la cambia un administrador desde Usuarios.
