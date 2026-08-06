<?php

namespace App\Support;

use RuntimeException;

/**
 * El gestor documental no pudo guardar o entregar un archivo.
 *
 * Existe para que los controladores puedan distinguir "el deposito fallo" de
 * cualquier otro error y contestar con un mensaje en vez de un 500: que
 * Cloudinary este caido no tiene que dejar el expediente inaccesible.
 */
class DocumentoNoDisponible extends RuntimeException {}
