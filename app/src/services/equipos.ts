import { api } from './api';

/**
 * YA IMPLEMENTADO — capa de acceso a la API de equipos (contrato del Examen I).
 * Las vistas llaman a estas funciones; nunca usan fetch directamente.
 */
export type Estado = 'disponible' | 'prestado' | 'mantenimiento';

export interface Categoria { id: number; nombre: string }

export interface Equipo {
  id: number;
  codigo: string;
  nombre: string;
  estado: Estado;
  fecha_adquisicion: string;
  categoria: Categoria;
}

export interface Pagina<T> {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

/** Lo que se envía al crear o actualizar. costo_interno no viaja de vuelta: EquipoResource lo oculta. */
export interface DatosEquipo {
  codigo: string;
  nombre: string;
  categoria_id: number | null;
  estado?: 'disponible' | 'mantenimiento';
  fecha_adquisicion: string;
  costo_interno: number | null;
}

export const POR_PAGINA = 15;

export const listar = (page = 1, q = '') =>
  api<Pagina<Equipo>>('/equipos', { params: { page, q, per_page: POR_PAGINA } });

export const obtener = (id: number | string) =>
  api<{ data: Equipo }>(`/equipos/${id}`).then((r) => r.data);

export const crear = (datos: DatosEquipo) =>
  api<{ data: Equipo }>('/equipos', { method: 'POST', body: datos }).then((r) => r.data);

export const actualizar = (id: number | string, datos: DatosEquipo) =>
  api<{ data: Equipo }>(`/equipos/${id}`, { method: 'PUT', body: datos }).then((r) => r.data);

export const eliminar = (id: number) => api<null>(`/equipos/${id}`, { method: 'DELETE' });

export const categorias = () => api<{ data: Categoria[] }>('/categorias').then((r) => r.data);
