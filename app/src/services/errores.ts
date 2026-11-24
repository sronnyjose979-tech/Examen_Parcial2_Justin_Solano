/**
 * YA IMPLEMENTADO — error único que lanza el cliente HTTP (services/api.ts).
 *
 *  status  → código HTTP (0 si no hubo respuesta: sin red, tiempo agotado, CORS)
 *  cuerpo  → JSON devuelto por la API (o null)
 *
 *  422 → cuerpo.errors   = { campo: ["mensaje", ...] }
 *  409 → cuerpo.mensaje  = "No se puede eliminar un equipo prestado."
 */
export class ErrorApi extends Error {
  constructor(
    public readonly status: number,
    public readonly cuerpo: any,
  ) {
    super(cuerpo?.mensaje ?? cuerpo?.message ?? `Error ${status}`);
    this.name = 'ErrorApi';
  }

  /** Primer mensaje de cada campo de un 422: { codigo: "El código ya existe." } */
  get erroresPorCampo(): Record<string, string> {
    const errores: Record<string, string[]> = this.cuerpo?.errors ?? {};
    return Object.fromEntries(Object.entries(errores).map(([campo, [mensaje]]) => [campo, mensaje]));
  }
}
