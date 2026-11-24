import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Identidad personal para el Examen II.
 */
const config: CapacitorConfig = {
  appId: 'cr.ac.ucr.if0009.c4l936',
  appName: 'Préstamos Solano',
  webDir: 'dist',
  android: {
    // Solo para el examen: la API local corre en http://10.0.2.2.
    // La excepción de texto en claro está acotada en res/xml/network_security_config.xml
    allowMixedContent: true,
  },
};

export default config;
