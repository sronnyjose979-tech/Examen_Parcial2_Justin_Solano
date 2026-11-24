import { toastController } from '@ionic/vue';

/** YA IMPLEMENTADO — mensaje breve en la parte inferior. */
export async function mostrarToast(mensaje: string, color: 'success' | 'warning' | 'danger' | 'medium' = 'medium') {
  const toast = await toastController.create({ message: mensaje, color, duration: 2500, position: 'bottom' });
  await toast.present();
}
