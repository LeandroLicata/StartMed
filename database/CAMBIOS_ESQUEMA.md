# Cambios al esquema original

Columnas agregadas por fuera del diseño database-first original (ver `CLAUDE.md`), para no
perderlas de vista si en algún momento hay que resincronizar con el modelo de datos fuente.

| Fecha      | Tabla.Columna                                      | Motivo                                                                                                  | Migración |
|------------|-----------------------------------------------------|-----------------------------------------------------------------------------------------------------------|-----------|
| 2026-08-02 | `AutoCirugiaEstado.observacionesAutoCirugiaEstado`   | Detalle de texto libre por paso del trámite de autorización (para el stepper manual de gestión, futuro). | `2026_07_31_100600_create_cirugia_relacionadas_tables.php` |
| 2026-08-02 | `Cirugia.fechaHoraFinCirugia`                        | Hora de fin de la cirugía, para poder calcular conflictos de quirófano por rango en vez de solo por horario de inicio (lógica de rango todavía no implementada). | `2026_07_31_100500_create_cirugia_table.php` |
