// API simulada del contrato del Examen I · sin dependencias (node:http)
// Uso:  npm run mock            → http://localhost:3001/api   (emulador: http://10.0.2.2:3001/api)
//       MOCK_LATENCIA=800 npm run mock   → simula una red lenta (ms)
// Credenciales: docente@ucr.ac.cr · Examen2026!
// YA IMPLEMENTADO — no forma parte de lo que se evalúa.

import { createServer } from 'node:http';
import { randomBytes } from 'node:crypto';

const PUERTO = Number(process.env.MOCK_PUERTO ?? 3001);
const LATENCIA = Number(process.env.MOCK_LATENCIA ?? 250);

const USUARIO = { id: 1, nombre: 'Docente IF0009', email: 'docente@ucr.ac.cr', password: 'Examen2026!' };
const tokens = new Set();
const intentos = new Map();

const categorias = [
  { id: 1, nombre: 'Audio' },
  { id: 2, nombre: 'Cómputo' },
  { id: 3, nombre: 'Electrónica' },
  { id: 4, nombre: 'Proyección' },
  { id: 5, nombre: 'Redes' },
];

const nombres = [
  ['Laptop Dell Latitude 5440', 2], ['Laptop Lenovo ThinkPad E14', 2], ['MacBook Air M3', 2],
  ['Proyector Epson PowerLite X49', 4], ['Proyector BenQ MW560', 4], ['Pantalla retráctil 100"', 4],
  ['Kit Arduino Uno R4', 3], ['Kit Raspberry Pi 5', 3], ['Multímetro Fluke 117', 3], ['Osciloscopio Rigol DS1054Z', 3],
  ['Switch Cisco CBS250 24p', 5], ['Router MikroTik hAP ax²', 5], ['Access point Ubiquiti U6 Lite', 5], ['Kit de crimpado RJ45', 5],
  ['Parlante JBL EON 710', 1], ['Micrófono inalámbrico Shure BLX', 1], ['Consola Behringer Xenyx 802', 1],
  ['Laptop HP ProBook 440', 2], ['Tableta Samsung Galaxy Tab S9', 2], ['Cámara web Logitech C920', 2],
  ['Proyector portátil Anker Nebula', 4], ['Kit ESP32 DevKit', 3], ['Fuente de poder regulable', 3],
  ['Probador de cable de red', 5], ['Audífonos Sony WH-1000XM5', 1],
];

let siguienteId = 1;
const equipos = nombres.map(([nombre, categoria_id], i) => ({
  id: siguienteId++,
  codigo: `EQ-${String(i + 1).padStart(4, '0')}`,
  nombre,
  categoria_id,
  estado: i % 5 === 1 ? 'prestado' : i % 7 === 3 ? 'mantenimiento' : 'disponible',
  fecha_adquisicion: `2025-${String((i % 12) + 1).padStart(2, '0')}-${String((i % 27) + 1).padStart(2, '0')}`,
  costo_interno: 150000 + i * 12500,
}));

// EquipoResource: sin costo_interno ni marcas de tiempo
const recurso = (e) => ({
  id: e.id,
  codigo: e.codigo,
  nombre: e.nombre,
  estado: e.estado,
  fecha_adquisicion: e.fecha_adquisicion,
  categoria: categorias.find((c) => c.id === e.categoria_id),
});

const hoy = () => new Date().toISOString().slice(0, 10);

function validar(d, actual) {
  const errores = {};
  const agregar = (campo, msj) => (errores[campo] ??= []).push(msj);

  if (!d.codigo || typeof d.codigo !== 'string') agregar('codigo', 'El código es obligatorio.');
  else if (d.codigo.length > 20) agregar('codigo', 'El código no puede superar 20 caracteres.');
  else if (!/^EQ-\d{4}$/.test(d.codigo)) agregar('codigo', 'El código debe tener el formato EQ-0000.');
  else if (equipos.some((e) => e.codigo === d.codigo && e.id !== actual?.id)) agregar('codigo', 'Ya existe un equipo con ese código.');

  if (!d.nombre || typeof d.nombre !== 'string') agregar('nombre', 'El nombre es obligatorio.');
  else if (d.nombre.length < 3 || d.nombre.length > 120) agregar('nombre', 'El nombre debe tener entre 3 y 120 caracteres.');

  if (d.categoria_id === undefined || d.categoria_id === null || d.categoria_id === '') agregar('categoria_id', 'La categoría es obligatoria.');
  else if (!categorias.some((c) => c.id === Number(d.categoria_id))) agregar('categoria_id', 'La categoría seleccionada no existe.');

  if (d.estado !== undefined && d.estado !== null && !['disponible', 'mantenimiento'].includes(d.estado))
    agregar('estado', 'El estado solo puede ser disponible o mantenimiento.');

  if (!d.fecha_adquisicion) agregar('fecha_adquisicion', 'La fecha de adquisición es obligatoria.');
  else if (Number.isNaN(Date.parse(d.fecha_adquisicion))) agregar('fecha_adquisicion', 'La fecha de adquisición no es válida.');
  else if (d.fecha_adquisicion > hoy()) agregar('fecha_adquisicion', 'La fecha de adquisición no puede ser futura.');

  if (d.costo_interno === undefined || d.costo_interno === null || d.costo_interno === '') agregar('costo_interno', 'El costo interno es obligatorio.');
  else if (Number.isNaN(Number(d.costo_interno))) agregar('costo_interno', 'El costo interno debe ser numérico.');
  else if (Number(d.costo_interno) < 0) agregar('costo_interno', 'El costo interno no puede ser negativo.');

  return errores;
}

function enviar(res, estado, cuerpo, extra = {}) {
  const cabeceras = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Authorization, Content-Type, Accept',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Expose-Headers': 'Location',
    ...extra,
  };
  if (cuerpo === undefined) {
    res.writeHead(estado, cabeceras);
    return res.end();
  }
  res.writeHead(estado, { 'Content-Type': 'application/json; charset=utf-8', ...cabeceras });
  res.end(JSON.stringify(cuerpo));
}

const err422 = (res, errors) =>
  enviar(res, 422, { message: Object.values(errors)[0][0], errors });
const err409 = (res, mensaje) => enviar(res, 409, { mensaje, codigo: 'REGLA_NEGOCIO' });
const err404 = (res) => enviar(res, 404, { message: 'Recurso no encontrado.' });

async function leerCuerpo(req) {
  let texto = '';
  for await (const parte of req) texto += parte;
  try { return texto ? JSON.parse(texto) : {}; } catch { return {}; }
}

const servidor = createServer(async (req, res) => {
  if (req.method === 'OPTIONS') return enviar(res, 204);
  await new Promise((r) => setTimeout(r, LATENCIA));

  const url = new URL(req.url, `http://${req.headers.host}`);
  const ruta = url.pathname.replace(/\/+$/, '');
  console.log(`${new Date().toLocaleTimeString()}  ${req.method} ${url.pathname}${url.search}`);

  // ---- Público -------------------------------------------------------------
  if (req.method === 'POST' && ruta === '/api/login') {
    const ip = req.socket.remoteAddress;
    const ahora = Date.now();
    const lista = (intentos.get(ip) ?? []).filter((t) => ahora - t < 60_000);
    if (lista.length >= 10) return enviar(res, 429, { message: 'Too Many Attempts.' }, { 'Retry-After': '60' });
    intentos.set(ip, [...lista, ahora]);

    const { email, password } = await leerCuerpo(req);
    const errores = {};
    if (!email) errores.email = ['El correo es obligatorio.'];
    if (!password) errores.password = ['La contraseña es obligatoria.'];
    if (Object.keys(errores).length) return err422(res, errores);
    if (email !== USUARIO.email || password !== USUARIO.password)
      return err422(res, { email: ['Las credenciales proporcionadas son incorrectas.'] });

    const token = `${tokens.size + 1}|${randomBytes(20).toString('hex')}`;
    tokens.add(token);
    return enviar(res, 200, { token, usuario: { id: USUARIO.id, nombre: USUARIO.nombre } });
  }

  // ---- Protegido -----------------------------------------------------------
  const token = (req.headers.authorization ?? '').replace(/^Bearer\s+/i, '');
  if (!tokens.has(token)) return enviar(res, 401, { message: 'Unauthenticated.' });

  if (req.method === 'POST' && ruta === '/api/logout') {
    tokens.delete(token);
    return enviar(res, 204);
  }

  if (req.method === 'GET' && ruta === '/api/categorias') return enviar(res, 200, { data: categorias });

  if (ruta === '/api/equipos') {
    if (req.method === 'GET') {
      const q = (url.searchParams.get('q') ?? '').toLowerCase();
      const estado = url.searchParams.get('estado');
      const porPagina = Math.min(Math.max(Number(url.searchParams.get('per_page')) || 10, 1), 50);
      const lista = equipos
        .filter((e) => !q || e.codigo.toLowerCase().includes(q) || e.nombre.toLowerCase().includes(q))
        .filter((e) => !estado || e.estado === estado)
        .sort((a, b) => a.codigo.localeCompare(b.codigo));
      const total = lista.length;
      const ultima = Math.max(Math.ceil(total / porPagina), 1);
      const pagina = Math.min(Math.max(Number(url.searchParams.get('page')) || 1, 1), ultima);
      const data = lista.slice((pagina - 1) * porPagina, pagina * porPagina).map(recurso);
      const base = `${url.origin}/api/equipos?per_page=${porPagina}&page=`;
      return enviar(res, 200, {
        data,
        links: {
          first: base + 1, last: base + ultima,
          prev: pagina > 1 ? base + (pagina - 1) : null, next: pagina < ultima ? base + (pagina + 1) : null,
        },
        meta: {
          current_page: pagina, last_page: ultima, per_page: porPagina, total,
          from: total ? (pagina - 1) * porPagina + 1 : null, to: total ? (pagina - 1) * porPagina + data.length : null,
        },
      });
    }
    if (req.method === 'POST') {
      const d = await leerCuerpo(req);
      const errores = validar(d);
      if (Object.keys(errores).length) return err422(res, errores);
      const nuevo = {
        id: siguienteId++, codigo: d.codigo, nombre: d.nombre, categoria_id: Number(d.categoria_id),
        estado: d.estado ?? 'disponible', fecha_adquisicion: d.fecha_adquisicion, costo_interno: Number(d.costo_interno),
      };
      equipos.push(nuevo);
      return enviar(res, 201, { data: recurso(nuevo) }, { Location: `${url.origin}/api/equipos/${nuevo.id}` });
    }
  }

  const coincidencia = ruta.match(/^\/api\/equipos\/(\d+)(\/prestamo)?$/);
  if (coincidencia) {
    const equipo = equipos.find((e) => e.id === Number(coincidencia[1]));
    if (!equipo) return err404(res);

    if (coincidencia[2] && req.method === 'POST') {
      if (equipo.estado === 'mantenimiento') return err409(res, 'No se puede prestar un equipo en mantenimiento.');
      if (equipo.estado === 'prestado') return err409(res, 'El equipo ya se encuentra prestado.');
      equipo.estado = 'prestado';
      return enviar(res, 200, { data: recurso(equipo) });
    }
    if (!coincidencia[2]) {
      if (req.method === 'GET') return enviar(res, 200, { data: recurso(equipo) });
      if (req.method === 'PUT') {
        const d = await leerCuerpo(req);
        const errores = validar(d, equipo);
        if (Object.keys(errores).length) return err422(res, errores);
        Object.assign(equipo, {
          codigo: d.codigo, nombre: d.nombre, categoria_id: Number(d.categoria_id),
          fecha_adquisicion: d.fecha_adquisicion, costo_interno: Number(d.costo_interno),
          ...(d.estado && equipo.estado !== 'prestado' ? { estado: d.estado } : {}),
        });
        return enviar(res, 200, { data: recurso(equipo) });
      }
      if (req.method === 'DELETE') {
        if (equipo.estado === 'prestado') return err409(res, 'No se puede eliminar un equipo prestado.');
        equipos.splice(equipos.indexOf(equipo), 1);
        return enviar(res, 204);
      }
    }
  }

  return err404(res);
});

servidor.listen(PUERTO, '0.0.0.0', () => {
  console.log(`API simulada en http://localhost:${PUERTO}/api  (emulador: http://10.0.2.2:${PUERTO}/api)`);
  console.log(`Credenciales: ${USUARIO.email} · ${USUARIO.password}`);
});
