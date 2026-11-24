<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Equipos</ion-title>
        <ion-buttons slot="end">
          <ion-button router-link="/tabs/equipos/nuevo" aria-label="Nuevo equipo">
            <ion-icon slot="icon-only" :icon="add" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
      <ion-toolbar>
        <ion-searchbar v-model="q" :debounce="400" placeholder="Buscar por código o nombre" @ion-input="buscar" />
      </ion-toolbar>
      <!-- YA IMPLEMENTADO: depende de useConexion() [M7] -->
      <ion-toolbar v-if="!conectado" color="warning">
        <ion-title size="small">Sin conexión: los datos pueden estar desactualizados</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content>
      <ion-refresher slot="fixed" @ionRefresh="refrescar"><ion-refresher-content /></ion-refresher>
      <div v-if="cargando && pagina === 0" class="ion-padding">
        <ion-skeleton-text v-for="n in 6" :key="n" animated style="height: 64px; margin: 12px 0" />
      </div>
      <div v-else-if="error" class="estado-vacio">
        <p>{{ error }}</p><ion-button @click="cargar(true)">Reintentar</ion-button>
      </div>
      <div v-else-if="equipos.length === 0" class="estado-vacio">
        <ion-icon :icon="searchOutline" /><p>No se encontraron equipos.</p>
      </div>
      <ion-list v-else>
        <ion-item-sliding v-for="equipo in equipos" :key="equipo.id">
          <ion-item :router-link="`/tabs/equipos/${equipo.id}/editar`" button detail>
            <ion-label><h2>{{ equipo.nombre }}</h2><p>{{ equipo.codigo }} · {{ equipo.categoria.nombre }}</p></ion-label>
            <ion-badge slot="end" :color="colorEstado[equipo.estado]">{{ equipo.estado }}</ion-badge>
          </ion-item>
          <ion-item-options side="end"><ion-item-option color="danger" @click="confirmarEliminacion(equipo, $event)">
            <ion-icon slot="start" :icon="trashOutline" />Eliminar
          </ion-item-option></ion-item-options>
        </ion-item-sliding>
      </ion-list>
      <ion-infinite-scroll :disabled="!hayMas || cargando || !!error" @ionInfinite="cargarMas">
        <ion-infinite-scroll-content loading-text="Cargando más equipos…" />
      </ion-infinite-scroll>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonButtons, IonButton, IonIcon, IonSearchbar, IonContent,
  IonRefresher, IonRefresherContent, IonList, IonItem, IonLabel, IonSkeletonText, IonBadge,
  IonItemSliding, IonItemOptions, IonItemOption, IonInfiniteScroll, IonInfiniteScrollContent,
  alertController, onIonViewWillEnter,
} from '@ionic/vue';
import type { InfiniteScrollCustomEvent, RefresherCustomEvent } from '@ionic/vue';
import { add, searchOutline, trashOutline } from 'ionicons/icons';
import { eliminar, listar, type Equipo, type Estado } from '@/services/equipos';
import { ErrorApi } from '@/services/errores';
import { mostrarToast } from '@/services/avisos';
import { useConexion, vibrar } from '@/composables/useConexion';

const colorEstado: Record<Estado, string> = { disponible: 'success', prestado: 'warning', mantenimiento: 'medium' };

const { conectado } = useConexion();

const equipos = ref<Equipo[]>([]);
const pagina = ref(0);
const ultimaPagina = ref(1);
const q = ref('');
const cargando = ref(false);
const error = ref('');
const hayMas = computed(() => pagina.value < ultimaPagina.value);
let recargaPendiente = false;

/**
 * TODO [M4] cargar(reiniciar)
 *  - reiniciar = true  → pide la página 1 y REEMPLAZA la lista.
 *  - reiniciar = false → pide la página siguiente y la AGREGA al final.
 *  - Use listar(pagina, q) de services/equipos.ts y actualice pagina/ultimaPagina con meta.
 *  - Maneje cargando y error (un 401 ya lo resuelve api.ts).
 */
async function cargar(reiniciar = false) {
  if (cargando.value) {
    // Si cambia la búsqueda durante una petición, recargar al terminar.
    if (reiniciar) recargaPendiente = true;
    return;
  }
  cargando.value = true;
  error.value = '';
  const solicitada = reiniciar ? 1 : pagina.value + 1;
  try {
    const resultado = await listar(solicitada, q.value.trim());
    equipos.value = reiniciar ? resultado.data : [...equipos.value, ...resultado.data];
    pagina.value = resultado.meta.current_page;
    ultimaPagina.value = resultado.meta.last_page;
  } catch (e) {
    error.value = e instanceof ErrorApi ? e.message : 'No fue posible cargar los equipos.';
  } finally {
    cargando.value = false;
    if (recargaPendiente) {
      recargaPendiente = false;
      await cargar(true);
    }
  }
}

/**
 * TODO [M4] La lista debe recargarse CADA VEZ que la persona vuelve a esta página
 *           (por ejemplo, al regresar del formulario). Elija el hook de ciclo de vida correcto.
 */
onIonViewWillEnter(() => cargar(true));

const buscar = () => cargar(true);

/** TODO [M4] Recargar desde la página 1 y cerrar el refresher (evento.target.complete()). */
async function refrescar(evento: RefresherCustomEvent) {
  try { await cargar(true); } finally { await evento.target.complete(); }
}

/** TODO [M4] Cargar la página siguiente y cerrar el infinite scroll (evento.target.complete()). */
async function cargarMas(evento: InfiniteScrollCustomEvent) {
  try { if (hayMas.value) await cargar(false); } finally { await evento.target.complete(); }
}

/**
 * TODO [M6] confirmarEliminacion(equipo, evento)
 *  1. Cierre el ion-item-sliding.
 *  2. alertController con dos botones: role 'cancel' y role 'destructive'.
 *     Lea el role con onDidDismiss(); si no es 'destructive', no haga nada.
 *  3. eliminar(equipo.id):
 *       204 → quitar el equipo de la lista (sin recargar todo), vibrar() y toast de éxito.
 *       409 → toast con el mensaje que envía la API (regla R1: equipo prestado). El equipo sigue en la lista.
 *       404 → quitarlo de la lista y avisar que ya no existe.
 */
async function confirmarEliminacion(equipo: Equipo, evento: Event) {
  const opcion = evento.target as HTMLElement;
  const deslizable = opcion.closest('ion-item-sliding');
  await deslizable?.close();
  const alerta = await alertController.create({
    header: 'Eliminar equipo', message: `¿Desea eliminar ${equipo.nombre}?`,
    buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Eliminar', role: 'destructive' }],
  });
  await alerta.present();
  const { role } = await alerta.onDidDismiss();
  if (role !== 'destructive') return;
  try {
    await eliminar(equipo.id);
    equipos.value = equipos.value.filter((e) => e.id !== equipo.id);
    await vibrar(); await mostrarToast('Equipo eliminado.', 'success');
  } catch (e) {
    if (e instanceof ErrorApi && e.status === 404) {
      equipos.value = equipos.value.filter((equipoActual) => equipoActual.id !== equipo.id);
      await mostrarToast('El equipo ya no existe.', 'medium');
    } else if (e instanceof ErrorApi && e.status === 409) {
      await mostrarToast(e.message, 'warning');
    } else {
      await mostrarToast(e instanceof Error ? e.message : 'No fue posible eliminar el equipo.', 'danger');
    }
  }
}

</script>
